<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * SRS FR-002 - password change and reset. Passwords are hashed by the model's
 * "hashed" cast and are never stored in plain text (SRS 7.2).
 */
class PasswordController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    /** FR-002 - users receive a password reset notification. */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        // The same message is shown either way so the form cannot be used to
        // discover which addresses are registered (SRS 7.2).
        return back()->with('status', $status === Password::RESET_LINK_SENT
            ? 'If that address is registered, a password reset link has been sent to it.'
            : 'If that address is registered, a password reset link has been sent to it.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->writeAuditLog('password.reset', [], [], 'Password reset via emailed link');
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. Please sign in.');
    }

    public function changeForm(): View
    {
        return view('auth.change-password');
    }

    public function change(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = Auth::user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Your current password is incorrect.',
            ]);
        }

        $user->forceFill(['password' => $validated['password']])->save();
        $user->writeAuditLog('password.changed', [], [], 'Password changed by the account holder');

        return redirect()
            ->route($user->isShopOwner() ? 'portal.dashboard' : 'admin.dashboard')
            ->with('status', 'Your password has been updated.');
    }
}
