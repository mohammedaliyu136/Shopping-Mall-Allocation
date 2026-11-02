<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\WardConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request, WardConfigRepository $configs)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Those details do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();

        // Phase 3: load the home ward's config once at login.
        if ($user->ward_id) {
            $request->session()->put('ward_id', $user->ward_id);
            $configs->for($user->ward_id);

            return redirect()->intended(route('wards.show', $user->ward_id));
        }

        return redirect()->intended($user->isAdmin() ? route('admin.wards.index') : route('wards.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
