<?php

namespace App\Http\Controllers;

use App\Models\Encounter;
use App\Services\WardConfigRepository;
use App\Ward\Actions\ActionRegistry;
use App\Ward\Actions\WardAction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Opens and submits the action button forms. */
class ActionController extends Controller
{
    public function __construct(private ActionRegistry $actions, private WardConfigRepository $configs) {}

    private function resolve(Request $request, Encounter $encounter, string $key): WardAction
    {
        abort_unless($this->actions->has($key), 404);
        $action = $this->actions->find($key);
        $user = $request->user();
        abort_unless($action->role() === $user->role, 403, 'This action belongs to another role.');

        $config = $this->configs->for($encounter->ward_id);
        $enabled = in_array($key, $config->actionsFor($user->role), true) && $action->supports($config->ward_type);

        // A nurse on the receiving ward may always accept an incoming transfer.
        $receiving = $key === 'move_patient' && $encounter->pendingTransfer
            && session('ward_id') === $encounter->pendingTransfer->to_ward_id;

        abort_unless($enabled || $receiving, 403, 'This action is not enabled on this ward.');

        return $action;
    }

    /** The form fragment shown in the action dialog. */
    public function form(Request $request, Encounter $encounter, string $key)
    {
        $action = $this->resolve($request, $encounter, $key);
        $encounter->loadMissing(['patient', 'ward', 'bed', 'team']);

        return view('actions.dialog', [
            'action' => $action,
            'encounter' => $encounter,
            'unavailable' => $action->unavailableReason($encounter),
        ] + $action->viewData($encounter));
    }

    public function store(Request $request, Encounter $encounter, string $key)
    {
        $action = $this->resolve($request, $encounter, $key);

        if ($reason = $action->unavailableReason($encounter)) {
            return back()->withErrors(['action' => $reason]);
        }

        try {
            $data = $request->validate($action->rules($encounter));
            $message = $action->handle($encounter, $request->user(), $data);
        } catch (ValidationException $e) {
            // Re-open the same form with the user's input on the redirect.
            $request->session()->flash('open_action', $key);
            throw $e;
        }

        $encounter->refresh();
        $to = $encounter->isActive()
            ? route('encounters.show', $encounter)
            : route('wards.show', session('ward_id') ?? $encounter->ward_id);

        return redirect($to)->with('status', $message);
    }
}
