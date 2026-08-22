<?php

namespace App\Support;

/**
 * Minimal, dependency-free TOTP implementation (RFC 6238 / RFC 4648 base32).
 *
 * Compatible with Google Authenticator, Authy, 1Password, Microsoft
 * Authenticator, etc. — 6-digit codes, SHA-1 HMAC, 30-second step.
 */
class Totp
{
    public const STEP = 30;
    public const DIGITS = 6;

    private const B32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Generate a new random base32 secret (160 bits by default). */
    public static function generateSecret(int $chars = 32): string
    {
        $bytes = random_bytes((int) ceil($chars * 5 / 8));

        return substr(self::base32Encode($bytes), 0, $chars);
    }

    /** otpauth:// URI that authenticator apps can scan as a QR code. */
    public static function uri(string $accountName, string $secret, string $issuer = 'StudyAI'): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($accountName)
            .'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits='.self::DIGITS
            .'&period='.self::STEP;
    }

    /** Current 6-digit code for a base32 secret. */
    public static function code(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, self::STEP);
        $binaryCounter = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, self::base32Decode($secret), true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Constant-time verification with a ±1 step window to tolerate clock drift. */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return false;
        }

        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            $expected = self::code($secret, $now + $i * self::STEP);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /** A batch of single-use recovery codes ("xxxxx-xxxxx"). */
    public static function recoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtolower(str_split(bin2hex(random_bytes(5)), 5)[0].'-'.str_split(bin2hex(random_bytes(5)), 5)[0]);
        }

        return $codes;
    }

    public static function base32Encode(string $bytes): string
    {
        $result = '';
        $bits = 0;
        $value = 0;

        foreach (str_split($bytes) as $byte) {
            $value = ($value << 8) | ord($byte);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $result .= self::B32_ALPHABET[($value >> $bits) & 31];
            }
        }

        if ($bits > 0) {
            $result .= self::B32_ALPHABET[($value << (5 - $bits)) & 31];
        }

        return $result;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32) ?? '');
        $result = '';
        $bits = 0;
        $value = 0;

        foreach (str_split($b32) as $char) {
            $pos = strpos(self::B32_ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $value = ($value << 5) | $pos;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $result .= chr(($value >> $bits) & 0xFF);
            }
        }

        return $result;
    }
}
