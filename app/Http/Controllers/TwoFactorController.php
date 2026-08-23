<?php

namespace App\Http\Controllers;

use App\Support\TwoFactor\TotpAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * TOTP enrolment for school users.
 *
 * Enable → scan/confirm → confirmed. The secret is written immediately but is
 * not honoured at login until the user proves they can generate a valid code,
 * so nobody can lock themselves out with a mis-scanned QR.
 */
class TwoFactorController extends Controller
{
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back()->with('status', 'Two-factor authentication is already enabled.');
        }

        $secret = $user->startTwoFactorEnrollment();

        // Held in the session only for the length of the enrolment flow.
        $request->session()->put('2fa:setup:secret', $secret);

        return redirect()
            ->route('profile.edit', ['tab' => 'security'])
            ->with('status', 'Scan the QR code, then enter a code to finish.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user->hasTwoFactorPending()) {
            return back()->withErrors(['code' => 'Start the setup again.']);
        }

        if (! $user->verifyTwoFactorCode($request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'That code is not valid. Check your device clock and try again.',
            ]);
        }

        $user->confirmTwoFactor();
        $request->session()->forget('2fa:setup:secret');
        $request->session()->flash('2fa:show-recovery', true);

        return redirect()
            ->route('profile.edit', ['tab' => 'security'])
            ->with('status', 'Two-factor authentication is on. Save your recovery codes.');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();

        abort_unless($user->hasTwoFactorEnabled(), 400);

        $user->replaceRecoveryCodes();
        $request->session()->flash('2fa:show-recovery', true);

        return redirect()
            ->route('profile.edit', ['tab' => 'security'])
            ->with('status', 'New recovery codes generated.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->user()->disableTwoFactor();
        $request->session()->forget('2fa:setup:secret');

        return redirect()
            ->route('profile.edit', ['tab' => 'security'])
            ->with('status', 'Two-factor authentication disabled.');
    }
}
