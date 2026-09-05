<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** SRS 6.15 - staff-side support ticket handling. */
class TicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function index(Request $request): View
    {
        $tickets = SupportTicket::query()
            ->with(['owner', 'shop.block', 'assignee'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('category')->toString(), fn ($q, $c) => $q->where('category', $c))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($sub) => $sub->where('ticket_number', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%")
            ))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'statuses' => SupportTicket::STATUSES,
            'categories' => SupportTicket::CATEGORIES,
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load(['owner', 'shop.block', 'assignee', 'messages.user', 'messages.owner', 'attachments']);

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'officers' => User::query()
                ->whereHas('role', fn ($q) => $q->whereNot('slug', Role::SHOP_OWNER))
                ->where('is_active', true)
                ->orderBy('name')->get(),
            'statuses' => SupportTicket::STATUSES,
        ]);
    }

    /** FR-143 - reply on the ticket; internal notes stay hidden from the owner. */
    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $this->tickets->reply(
            $ticket,
            $validated['body'],
            Auth::user(),
            null,
            $request->boolean('is_internal'),
        );

        return back()->with('status', 'Reply added to the ticket.');
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
            'assigned_department' => ['nullable', 'string', 'max:100'],
        ]);

        $this->tickets->assign(
            $ticket,
            $validated['assigned_to'] ? User::find($validated['assigned_to']) : null,
            $validated['assigned_department'] ?? null,
        );

        return back()->with('status', 'Ticket assignment updated.');
    }

    /** FR-142 - move the ticket through its lifecycle. */
    public function changeStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(SupportTicket::STATUSES)],
        ]);

        $this->tickets->changeStatus($ticket, $validated['status']);

        return back()->with('status', 'Ticket status updated.');
    }
}
