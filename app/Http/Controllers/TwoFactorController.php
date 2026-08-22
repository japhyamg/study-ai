<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Manage two-factor authentication from the profile page:
 *   enable → show QR/secret → confirm with a code → recovery codes shown once.
 */
class TwoFactorController extends Controller
{
    /** Step 1 — generate a secret and ask the user to scan it. */
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->requireCurrentPassword($request, $user);

        $secret = Totp::generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('profile.edit', ['two_factor' => 'setup'])
            ->with('status', 'Scan the QR code with your authenticator app, then confirm with a code.');
    }

    /** Step 2 — verify a code from the app; activate 2FA and issue recovery codes. */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return redirect()->route('profile.edit');
        }

        $data = $request->validate([
            'code' => ['required', 'string'],
        ], [
            'code.required' => 'Enter the 6-digit code from your app.',
        ]);

        if (! Totp::verify($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'That code did not match. Check your authenticator app and try again.',
            ])->redirectTo(route('profile.edit', ['two_factor' => 'setup']));
        }

        $codes = Totp::recoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();

        return redirect()->route('profile.edit', ['two_factor' => 'codes'])
            ->with('status', 'Two-factor authentication is now enabled.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Cancelling an unconfirmed setup is safe without a password re-prompt;
        // disabling an ACTIVE 2FA always requires the current password.
        if ($user->two_factor_secret && ! $user->hasTwoFactorEnabled()) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            return redirect()->route('profile.edit')
                ->with('status', 'Two-factor setup cancelled.');
        }

        $this->requireCurrentPassword($request, $user);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('profile.edit')
            ->with('status', 'Two-factor authentication disabled.');
    }

    /** Issue a fresh batch of recovery codes (old ones stop working). */
    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();

        $this->requireCurrentPassword($request, $user);

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('profile.edit');
        }

        $user->forceFill([
            'two_factor_recovery_codes' => Totp::recoveryCodes(),
        ])->save();

        return redirect()->route('profile.edit', ['two_factor' => 'codes'])
            ->with('status', 'New recovery codes generated. Store them somewhere safe.');
    }

    protected function requireCurrentPassword(Request $request, User $user): void
    {
        $request->validate([
            'current_password' => ['required', 'string'],
        ], [
            'current_password.required' => 'Enter your current password to continue.',
        ]);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The provided password does not match your current password.',
            ]);
        }
    }
}
