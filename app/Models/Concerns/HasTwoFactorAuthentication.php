<?php

namespace App\Models\Concerns;

use App\Support\TwoFactor\TotpAuthenticator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

/**
 * TOTP two-factor support.
 *
 * Column names match Laravel Fortify exactly (`two_factor_secret`,
 * `two_factor_recovery_codes`, `two_factor_confirmed_at`) and the payloads are
 * encrypted the same way, so Fortify can be installed later and will read this
 * data without a migration. The verification maths lives in
 * {@see TotpAuthenticator} so we have no hard package dependency today.
 */
trait HasTwoFactorAuthentication
{
    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret)
            && ! is_null($this->two_factor_confirmed_at);
    }

    /** 2FA started but the first code has not been confirmed yet. */
    public function hasTwoFactorPending(): bool
    {
        return ! is_null($this->two_factor_secret)
            && is_null($this->two_factor_confirmed_at);
    }

    public function decryptedTwoFactorSecret(): ?string
    {
        if (is_null($this->two_factor_secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->two_factor_secret);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return Collection<int, string> */
    public function recoveryCodes(): Collection
    {
        if (is_null($this->two_factor_recovery_codes)) {
            return collect();
        }

        try {
            return collect(json_decode(Crypt::decryptString($this->two_factor_recovery_codes), true) ?: []);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Generate a fresh (unconfirmed) secret + recovery codes. */
    public function startTwoFactorEnrollment(): string
    {
        $secret = TotpAuthenticator::generateSecret();

        $this->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(
                TotpAuthenticator::generateRecoveryCodes()
            )),
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secret;
    }

    /** Verify a 6-digit code against the pending/active secret. */
    public function verifyTwoFactorCode(string $code): bool
    {
        $secret = $this->decryptedTwoFactorSecret();

        return $secret !== null && TotpAuthenticator::verify($secret, $code);
    }

    /** Consume a one-time recovery code; returns false if it was not valid. */
    public function useRecoveryCode(string $code): bool
    {
        $code = trim($code);
        $codes = $this->recoveryCodes();

        if (! $codes->contains($code)) {
            return false;
        }

        $this->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(
                $codes->reject(fn ($c) => $c === $code)->values()->toJson()
            ),
        ])->save();

        return true;
    }

    public function replaceRecoveryCodes(): void
    {
        $this->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(
                TotpAuthenticator::generateRecoveryCodes()
            )),
        ])->save();
    }

    public function confirmTwoFactor(): void
    {
        $this->forceFill(['two_factor_confirmed_at' => now()])->save();
    }

    public function disableTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /** otpauth:// URI for the enrolment QR code. */
    public function twoFactorQrUri(string $secret, ?string $issuer = null): string
    {
        return TotpAuthenticator::provisioningUri(
            $secret,
            $this->email,
            $issuer ?: config('app.name', 'StudyAI')
        );
    }
}
