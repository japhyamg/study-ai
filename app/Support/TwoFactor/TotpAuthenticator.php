<?php

namespace App\Support\TwoFactor;

/**
 * RFC 6238 (TOTP) / RFC 4226 (HOTP) implementation.
 *
 * Self-contained so 2FA works without pulling a vendor package. It is
 * wire-compatible with Google Authenticator, 1Password, Authy, Microsoft
 * Authenticator, etc., and produces the same base32 secret format Laravel
 * Fortify uses — so swapping in Fortify later needs no data migration.
 */
final class TotpAuthenticator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Seconds per code. */
    public const PERIOD = 30;

    /** Code length. */
    public const DIGITS = 6;

    /** How many periods either side of "now" are accepted (clock drift). */
    public const WINDOW = 1;

    /** Generate a random base32 secret (160 bits, the RFC-recommended size). */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /** @return array<int, string> */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = self::randomToken(5).'-'.self::randomToken(5);
        }

        return $codes;
    }

    /** Compute the TOTP code for a given secret at a point in time. */
    public static function code(string $secret, ?int $timestamp = null, int $offset = 0): string
    {
        $key = self::base32Decode($secret);

        if ($key === '') {
            return str_repeat('0', self::DIGITS);
        }

        $counter = intdiv($timestamp ?? time(), self::PERIOD) + $offset;

        // 8-byte big-endian counter
        $binCounter = pack('N*', 0, $counter);

        $hash = hash_hmac('sha1', $binCounter, $key, true);

        // Dynamic truncation (RFC 4226 §5.3)
        $tail = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$tail]) & 0x7F) << 24) |
            ((ord($hash[$tail + 1]) & 0xFF) << 16) |
            ((ord($hash[$tail + 2]) & 0xFF) << 8) |
            (ord($hash[$tail + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Constant-time verification across the accepted drift window. */
    public static function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $valid = false;

        // Loop the whole window (no early return) to keep timing flat.
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            if (hash_equals(self::code($secret, $timestamp, $i), $code)) {
                $valid = true;
            }
        }

        return $valid;
    }

    /** Build the otpauth:// URI an authenticator app scans. */
    public static function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);

        return "otpauth://totp/{$label}?{$query}";
    }

    /** Human-friendly secret, grouped in fours, for manual entry. */
    public static function formatForDisplay(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    // ───────────────────────── base32 ─────────────────────────

    public static function base32Encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    public static function base32Decode(string $secret): string
    {
        $secret = rtrim(strtoupper(preg_replace('/[^A-Z2-7=]/i', '', $secret) ?? ''), '=');

        if ($secret === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                return '';
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }

    private static function randomToken(int $length): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no ambiguous 0/O/1/I
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $out;
    }
}
