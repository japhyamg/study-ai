<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** TOTP enrolment for platform staff. */
class SuperAdminTwoFactorController extends Controller
{
    public function enable(Request $request): RedirectResponse
    {
        $admin = $request->user('superadmin');

        if ($admin->hasTwoFactorEnabled()) {
            return back()->with('status', 'Two-factor authentication is already enabled.');
        }

        $secret = $admin->startTwoFactorEnrollment();
        $request->session()->put('2fa:setup:secret', $secret);

        return redirect()
            ->route('super-admin.profile.edit', ['tab' => 'security'])
            ->with('status', 'Scan the QR code, then enter a code to finish.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $admin = $request->user('superadmin');

        if (! $admin->hasTwoFactorPending()) {
            return back()->withErrors(['code' => 'Start the setup again.']);
        }

        if (! $admin->verifyTwoFactorCode($request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'That code is not valid. Check your device clock and try again.',
            ]);
        }

        $admin->confirmTwoFactor();
        $request->session()->forget('2fa:setup:secret');
        $request->session()->flash('2fa:show-recovery', true);

        return redirect()
            ->route('super-admin.profile.edit', ['tab' => 'security'])
            ->with('status', 'Two-factor authentication is on. Save your recovery codes.');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password:superadmin']]);

        $admin = $request->user('superadmin');
        abort_unless($admin->hasTwoFactorEnabled(), 400);

        $admin->replaceRecoveryCodes();
        $request->session()->flash('2fa:show-recovery', true);

        return redirect()
            ->route('super-admin.profile.edit', ['tab' => 'security'])
            ->with('status', 'New recovery codes generated.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password:superadmin']]);

        $request->user('superadmin')->disableTwoFactor();
        $request->session()->forget('2fa:setup:secret');

        return redirect()
            ->route('super-admin.profile.edit', ['tab' => 'security'])
            ->with('status', 'Two-factor authentication disabled.');
    }
}
