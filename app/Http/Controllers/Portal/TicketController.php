<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** SRS FR-140 to FR-143 - owner-side support tickets. */
class TicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function index(): View
    {
        return view('portal.tickets.index', [
            'tickets' => Auth::user()->owner->tickets()
                ->with('shop')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        $owner = Auth::user()->owner;

        return view('portal.tickets.form', [
            'shops' => $owner->allocations()
                ->whereIn('status', Allocation::OCCUPYING_STATUSES)
                ->with('shop.block')
                ->get()
                ->pluck('shop')
                ->filter(),
            'categories' => SupportTicket::CATEGORIES,
            'priorities' => SupportTicket::PRIORITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $owner = Auth::user()->owner;

        $validated = $request->validate([
            'category' => ['required', Rule::in(SupportTicket::CATEGORIES)],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(SupportTicket::PRIORITIES)],
            'shop_id' => ['nullable', 'exists:shops,id'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $ticket = $this->tickets->open($owner, $validated);

        if ($file = $request->file('attachment')) {
            $this->tickets->attach($ticket, $file->store('tickets/attachments'), $file->getClientOriginalName());
        }

        return redirect()->route('portal.tickets.show', $ticket)
            ->with('status', 'Ticket '.$ticket->ticket_number.' submitted.');
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorizeOwnership($ticket);

        // Internal staff notes stay out of the owner's view (FR-143).
        $ticket->load([
            'shop.block', 'assignee',
            'messages' => fn ($q) => $q->where('is_internal', false)->with(['user', 'owner']),
            'attachments',
        ]);

        return view('portal.tickets.show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeOwnership($ticket);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $this->tickets->reply($ticket, $validated['body'], null, Auth::user()->owner);

        return back()->with('status', 'Your response has been added to the ticket.');
    }

    private function authorizeOwnership(SupportTicket $ticket): void
    {
        abort_if($ticket->owner_id !== Auth::user()->owner_id, 403);
    }
}
