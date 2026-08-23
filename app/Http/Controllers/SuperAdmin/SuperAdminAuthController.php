<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Login for platform staff, on the central domain and the `superadmin` guard. */
class SuperAdminAuthController extends Controller
{
    public function create(): View
    {
        return view('super-admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key),
                    'minutes' => ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        $admin = SuperAdmin::where('email', $request->string('email')->lower()->toString())->first();

        if (! $admin || ! Hash::check($request->string('password')->toString(), $admin->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        if (! $admin->is_active) {
            throw ValidationException::withMessages(['email' => 'This account has been deactivated.']);
        }

        RateLimiter::clear($key);

        if ($admin->hasTwoFactorEnabled()) {
            $request->session()->put([
                '2fa:superadmin:id' => $admin->id,
                '2fa:superadmin:remember' => $request->boolean('remember'),
            ]);

            return redirect()->route('super-admin.two-factor.challenge');
        }

        Auth::guard('superadmin')->login($admin, $request->boolean('remember'));
        $request->session()->regenerate();

        $this->recordLogin($admin, $request);

        return redirect()->intended(route('super-admin.dashboard'));
    }

    public function twoFactorChallenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('2fa:superadmin:id')) {
            return redirect()->route('super-admin.login');
        }

        return view('super-admin.auth.two-factor-challenge');
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $id = $request->session()->get('2fa:superadmin:id');

        if (! $id) {
            return redirect()->route('super-admin.login');
        }

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if (! $request->filled('code') && ! $request->filled('recovery_code')) {
            throw ValidationException::withMessages(['code' => 'Enter your authentication code.']);
        }

        $key = '2fa-sa|'.$id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $admin = SuperAdmin::find($id);

        if (! $admin) {
            $request->session()->forget(['2fa:superadmin:id', '2fa:superadmin:remember']);

            return redirect()->route('super-admin.login');
        }

        $passed = $request->filled('recovery_code')
            ? $admin->useRecoveryCode($request->string('recovery_code')->toString())
            : $admin->verifyTwoFactorCode($request->string('code')->toString());

        if (! $passed) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }

        RateLimiter::clear($key);

        $remember = (bool) $request->session()->pull('2fa:superadmin:remember', false);
        $request->session()->forget('2fa:superadmin:id');

        Auth::guard('superadmin')->login($admin, $remember);
        $request->session()->regenerate();

        $this->recordLogin($admin, $request);

        return redirect()->intended(route('super-admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('superadmin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('super-admin.login');
    }

    private function throttleKey(Request $request): string
    {
        return 'sa|'.Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());
    }

    private function recordLogin(SuperAdmin $admin, Request $request): void
    {
        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();
    }
}
