<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Find and verify the user *within the active tenant*.
     *
     * Credentials are never matched globally, so two schools may each have a
     * user with the same email address.
     */
    public function resolveUser(): User
    {
        $this->ensureIsNotRateLimited();

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        $user = User::query()
            ->when($tenant, fn ($q) => $q->where('school_id', $tenant->id))
            ->where('email', $this->string('email')->lower()->toString())
            ->first();

        // Hash even on a miss so response timing doesn't reveal account existence.
        if (! $user || ! Hash::check($this->string('password')->toString(), $user->password)) {
            if (! $user) {
                Hash::make($this->string('password')->toString());
            }

            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Please contact your school administrator.',
            ]);
        }

        return $user;
    }

    /**
     * Kept so any legacy callers keep working.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $user = $this->resolveUser();

        auth()->login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /** Throttle per email + tenant + IP. */
    public function throttleKey(): string
    {
        $tenant = app()->bound('tenant') ? app('tenant')?->id : 'central';

        return Str::transliterate(
            Str::lower($this->string('email')).'|'.$tenant.'|'.$this->ip()
        );
    }
}
