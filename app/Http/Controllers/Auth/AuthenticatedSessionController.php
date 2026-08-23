<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Single login route for every school user type.
 *
 * The tenant is already resolved from the subdomain, so credentials are
 * matched within that school only — the same email can exist at two schools
 * without collision. Role dispatch happens after authentication.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->resolveUser();

        // Hold the session back when 2FA is on — log in only after the challenge.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put([
                '2fa:user:id' => $user->id,
                '2fa:remember' => $request->boolean('remember'),
            ]);

            RateLimiter::clear($request->throttleKey());

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));

        RateLimiter::clear($request->throttleKey());
        $request->session()->regenerate();

        $this->recordLogin($user, $request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** Show the TOTP challenge for a half-authenticated user. */
    public function twoFactorChallenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('2fa:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    /** Verify a TOTP or recovery code and complete the login. */
    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('2fa:user:id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if (! $request->filled('code') && ! $request->filled('recovery_code')) {
            throw ValidationException::withMessages([
                'code' => 'Enter your authentication code.',
            ]);
        }

        $key = '2fa|'.$userId.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $user = User::find($userId);

        if (! $user) {
            $request->session()->forget(['2fa:user:id', '2fa:remember']);

            return redirect()->route('login');
        }

        $passed = $request->filled('recovery_code')
            ? $user->useRecoveryCode($request->string('recovery_code')->toString())
            : $user->verifyTwoFactorCode($request->string('code')->toString());

        if (! $passed) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages([
                'code' => 'That code is not valid.',
            ]);
        }

        RateLimiter::clear($key);

        $remember = (bool) $request->session()->pull('2fa:remember', false);
        $request->session()->forget('2fa:user:id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $this->recordLogin($user, $request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function recordLogin(User $user, Request $request): void
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();
    }
}
