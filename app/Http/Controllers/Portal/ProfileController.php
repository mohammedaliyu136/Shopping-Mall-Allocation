<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SRS FR-152 - owners may update permitted personal information.
 *
 * Identity and business registration details are not editable here: those are
 * the "sensitive changes" the SRS routes through administrative approval, so
 * the portal raises a request rather than writing them directly.
 */
class ProfileController extends Controller
{
    /** The fields an owner may change without administrative approval. */
    private const SELF_SERVICE_FIELDS = ['phone', 'email', 'address', 'nok_name', 'nok_relationship', 'nok_phone', 'nok_address'];

    public function edit(): View
    {
        return view('portal.profile', ['owner' => Auth::user()->owner]);
    }

    public function update(Request $request): RedirectResponse
    {
        $owner = Auth::user()->owner;

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('owners')->ignore($owner)],
            'address' => ['nullable', 'string', 'max:255'],
            'nok_name' => ['nullable', 'string', 'max:150'],
            'nok_relationship' => ['nullable', 'string', 'max:100'],
            'nok_phone' => ['nullable', 'string', 'max:50'],
            'nok_address' => ['nullable', 'string', 'max:255'],
        ]);

        $owner->update(array_intersect_key($validated, array_flip(self::SELF_SERVICE_FIELDS)));

        return redirect()->route('portal.profile.edit')->with('status', 'Your details have been updated.');
    }
}
