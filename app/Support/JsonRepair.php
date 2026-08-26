<?php

namespace App\Support;

/**
 * Recovery for JSON that a language model didn't finish writing.
 *
 * The usual failure is hitting max_tokens mid-array, leaving something like:
 *
 *     {"questions":[{"text":"What is ...","options":["a","b
 *
 * Rather than discarding a response that is 90% usable, we walk it once,
 * tracking string and container state, cut back to the last point where a
 * value was structurally complete, and close whatever is still open.
 *
 * Note on approach: counting brackets across the whole document (the obvious
 * implementation) miscounts every bracket that appears inside a string value —
 * "use array[0]" being the common case — and produces JSON that is differently
 * broken. This tracks a cut point per container instead, so a truncated value
 * is dropped rather than half-included.
 */
final class JsonRepair
{
    /**
     * Strip control bytes that json_decode rejects outright.
     *
     * Keeps tab/newline/carriage-return and everything from 0x20 up, including
     * multi-byte UTF-8 continuation bytes.
     */
    public static function stripControlBytes(string $json): string
    {
        $out = '';
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $ord = ord($json[$i]);

            if ($ord >= 0x20 || $ord === 0x09 || $ord === 0x0A || $ord === 0x0D) {
                $out .= $json[$i];
            }
        }

        return $out;
    }

    /** Remove ```json fences a model added despite being told not to. */
    public static function stripFences(string $text): string
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*\n?/', '', $text) ?? $text;
            $text = preg_replace('/\n?```\s*$/', '', $text) ?? $text;
        }

        return trim($text);
    }

    /**
     * Attempt to close a truncated document.
     *
     * @return array<mixed>|null decoded value, or null if unrecoverable
     */
    public static function repair(string $json): ?array
    {
        $json = rtrim($json);

        if ($json === '') {
            return null;
        }

        // It may simply be valid already.
        $decoded = json_decode($json, true, 512, JSON_INVALID_UTF8_IGNORE);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $stack = self::scan($json);

        // Nothing was ever opened — this is prose, not truncated JSON.
        if ($stack === []) {
            return null;
        }

        // Drop trailing containers that never received a complete member,
        // so we don't emit a bare {} as a real array element.
        while (count($stack) > 1) {
            $last = $stack[count($stack) - 1];

            if ($last['safe'] !== $last['open_end']) {
                break;
            }

            array_pop($stack);
        }

        $candidate = self::trimDanglingSyntax(
            substr($json, 0, $stack[count($stack) - 1]['safe'])
        );

        // Close the open containers, innermost first.
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            $candidate .= $stack[$i]['char'] === '[' ? ']' : '}';
        }

        $decoded = json_decode($candidate, true, 512, JSON_INVALID_UTF8_IGNORE);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    /**
     * Single pass over the document.
     *
     * Returns the still-open containers. Each frame carries `safe`: the offset
     * just past the last *complete* member of that container. Cutting there is
     * always structurally sound.
     *
     * @return list<array{char: string, safe: int, open_end: int, saw_colon: bool}>
     */
    private static function scan(string $json): array
    {
        $stack = [];
        $inString = false;
        $escaped = false;
        $tokenStart = -1;
        $length = strlen($json);

        // A value finished at $end (exclusive) inside the innermost container.
        // In an object this only counts after a colon — otherwise what just
        // closed was a key, and a key alone is not a cut point.
        $valueComplete = static function (int $end) use (&$stack): void {
            if ($stack === []) {
                return;
            }

            $i = count($stack) - 1;

            if ($stack[$i]['char'] === '[') {
                $stack[$i]['safe'] = $end;

                return;
            }

            if ($stack[$i]['saw_colon']) {
                $stack[$i]['safe'] = $end;
                $stack[$i]['saw_colon'] = false;
            }
        };

        // Close a bare literal (number, true, false, null).
        $endToken = static function (int $at) use (&$tokenStart, $valueComplete): void {
            if ($tokenStart === -1) {
                return;
            }

            $tokenStart = -1;
            $valueComplete($at);
        };

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                    $valueComplete($i + 1);
                }

                continue;
            }

            if ($char === '"') {
                $endToken($i);
                $inString = true;

                continue;
            }

            if ($char === '{' || $char === '[') {
                $endToken($i);
                $stack[] = [
                    'char' => $char,
                    'safe' => $i + 1,
                    'open_end' => $i + 1,
                    'saw_colon' => false,
                ];

                continue;
            }

            if ($char === '}' || $char === ']') {
                $endToken($i);
                array_pop($stack);
                // The container just closed is itself a value in its parent.
                $valueComplete($i + 1);

                continue;
            }

            if ($char === ',') {
                $endToken($i);

                if ($stack !== []) {
                    // Cut *before* the comma; a trailing comma is invalid.
                    $stack[count($stack) - 1]['safe'] = $i;
                    $stack[count($stack) - 1]['saw_colon'] = false;
                }

                continue;
            }

            if ($char === ':') {
                $endToken($i);

                if ($stack !== []) {
                    $stack[count($stack) - 1]['saw_colon'] = true;
                }

                continue;
            }

            if (ctype_space($char)) {
                $endToken($i);

                continue;
            }

            if ($tokenStart === -1) {
                $tokenStart = $i;
            }
        }

        // A literal still open at EOF may itself be truncated ("350" cut to
        // "3"), so it is deliberately not completed here.

        return $stack;
    }

    /**
     * Drop a trailing comma, or a dangling `"key":` whose value never arrived.
     */
    private static function trimDanglingSyntax(string $json): string
    {
        $previous = null;

        // Each pattern can expose another, so run to a fixed point.
        while ($json !== $previous) {
            $previous = $json;
            $json = rtrim($json);
            $json = preg_replace('/,\s*$/', '', $json) ?? $json;
            $json = preg_replace('/"(?:[^"\\\\]|\\\\.)*"\s*:\s*$/', '', $json) ?? $json;
            $json = preg_replace('/,\s*$/', '', $json) ?? $json;
        }

        return rtrim($json);
    }
}
