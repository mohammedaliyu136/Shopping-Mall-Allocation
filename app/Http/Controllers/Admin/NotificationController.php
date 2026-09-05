<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Notification;
use App\Models\Owner;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** SRS 6.14 - notification creation, targeting and dispatch. */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(): View
    {
        return view('admin.notifications.index', [
            'notifications' => Notification::query()
                ->withCount('recipients')
                ->with('creator')
                ->latest('id')
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.notifications.form', [
            'owners' => Owner::query()->where('status', 'active')->orderBy('full_name')->get(),
            'blocks' => Block::query()->with('mall')->orderBy('name')->get(),
            'channels' => Notification::CHANNELS,
        ]);
    }

    /** FR-130 / FR-131 / FR-132 - compose, target and send. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            'channel' => ['required', Rule::in(Notification::CHANNELS)],
            'target_type' => ['required', Rule::in(Notification::TARGET_TYPES)],
            'target_ref' => ['nullable', 'string', 'max:255'],
            'send_now' => ['nullable', 'boolean'],
        ]);

        // Targeting a specific owner or block needs the reference to go with it.
        if (in_array($validated['target_type'], [Notification::TARGET_OWNER, Notification::TARGET_BLOCK], true)
            && blank($validated['target_ref'] ?? null)) {
            return back()->withInput()->withErrors([
                'target_ref' => 'Select the owner or block this notification is addressed to.',
            ]);
        }

        $notification = $this->notifications->create($validated['title'], $validated['message'], $validated);

        if ($request->boolean('send_now')) {
            $notification = $this->notifications->send($notification);
        }

        return redirect()->route('admin.notifications.show', $notification)->with(
            'status',
            $request->boolean('send_now')
                ? 'Notification sent to '.$notification->recipients()->count().' recipient(s).'
                : 'Notification saved as a draft.',
        );
    }

    public function show(Notification $notification): View
    {
        $notification->load(['recipients.owner', 'creator']);

        return view('admin.notifications.show', ['notification' => $notification]);
    }

    public function send(Notification $notification): RedirectResponse
    {
        $notification = $this->notifications->send($notification);

        $sent = $notification->recipients()->where('status', 'sent')->count();
        $failed = $notification->recipients()->where('status', 'failed')->count();

        return redirect()->route('admin.notifications.show', $notification)
            ->with('status', "Dispatched: {$sent} sent, {$failed} failed.");
    }
}
