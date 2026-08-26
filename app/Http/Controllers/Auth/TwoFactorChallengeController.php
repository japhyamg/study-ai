<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Second step of login for accounts with two-factor authentication enabled.
 * The first step (email + password) stashes the pending user id in the
 * session; this screen accepts a TOTP code or a recovery code.
 */
class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('2fa')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('2fa');

        if (! $pending) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'code' => ['required', 'string'],
        ], [
            'code.required' => 'Please enter your authentication code.',
        ]);

        $user = User::find($pending['id']);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget('2fa');

            return redirect()->route('login');
        }

        $code = $data['code'];

        $valid = Totp::verify($user->two_factor_secret, $code)
            || $user->useRecoveryCode($code);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'The provided two-factor code was invalid.',
            ]);
        }

        $request->session()->forget('2fa');

        Auth::login($user, $pending['remember'] ?? false);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** "Cancel" — abandon the pending login. */
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('2fa');

        return redirect()->route('login');
    }
}
