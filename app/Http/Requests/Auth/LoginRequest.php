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
            // Staff sign in with an email address, students with the admission
            // number their school issued, so the field takes either.
            'login' => ['required', 'string', 'max:255'],
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
        $login = trim($this->string('login')->toString());

        $user = User::query()
            ->when($tenant, fn ($q) => $q->where('users.school_id', $tenant->id))
            ->where(function ($q) use ($login, $tenant) {
                $q->whereRaw('LOWER(users.email) = ?', [mb_strtolower($login)]);

                // An admission number is only unique within a school, so it is
                // never accepted without a tenant to scope it to.
                if ($tenant) {
                    $q->orWhereExists(function ($sub) use ($login, $tenant) {
                        $sub->selectRaw('1')
                            ->from('student_profiles')
                            ->whereColumn('student_profiles.user_id', 'users.id')
                            ->where('student_profiles.school_id', $tenant->id)
                            ->whereRaw('LOWER(student_profiles.admission_number) = ?', [mb_strtolower($login)]);
                    });
                }
            })
            ->first();

        // Hash even on a miss so response timing doesn't reveal account existence.
        if (! $user || ! Hash::check($this->string('password')->toString(), $user->password)) {
            if (! $user) {
                Hash::make($this->string('password')->toString());
            }

            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => 'This account has been deactivated. Please contact your school administrator.',
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

    /** Throttle per identifier + tenant + IP. */
    public function throttleKey(): string
    {
        $tenant = app()->bound('tenant') ? app('tenant')?->id : 'central';

        return Str::transliterate(
            Str::lower($this->string('login')).'|'.$tenant.'|'.$this->ip()
        );
    }
}
