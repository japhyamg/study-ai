<?php

namespace App\Support\TwoFactor;

/**
 * Minimal QR Code (model 2) encoder producing an inline SVG.
 *
 * Scope is deliberately narrow — it only needs to encode short otpauth:// URIs
 * in byte mode at error-correction level L, which covers every 2FA enrolment
 * string. Keeping it in-house avoids a vendor dependency for one image.
 */
final class QrCode
{
    /** Alignment-pattern centre coordinates per version. */
    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62],
    ];

    /** Total data codewords available at EC level L, by version. */
    private const DATA_CODEWORDS_L = [
        1 => 19, 2 => 34, 3 => 55, 4 => 80, 5 => 108, 6 => 136, 7 => 156,
        8 => 194, 9 => 232, 10 => 274, 11 => 324, 12 => 370, 13 => 428,
    ];

    /** EC codewords per block, and block layout [count, ...] at level L. */
    private const EC_L = [
        1 => [7, [19]], 2 => [10, [34]], 3 => [15, [55]], 4 => [20, [80]],
        5 => [26, [108]], 6 => [18, [68, 68]], 7 => [20, [78, 78]],
        8 => [24, [97, 97]], 9 => [30, [116, 116]], 10 => [18, [68, 68, 69, 69]],
        11 => [20, [81, 81, 81, 81]], 12 => [24, [92, 92, 93, 93]],
        13 => [26, [107, 107, 107, 107]],
    ];

    /** Render an SVG string for the given text. */
    public static function svg(string $text, int $size = 200, string $dark = '#101828', string $light = '#FFFFFF'): string
    {
        $matrix = self::matrix($text);
        $n = count($matrix);
        $quiet = 4;
        $total = $n + $quiet * 2;

        $rects = '';
        for ($y = 0; $y < $n; $y++) {
            $x = 0;
            while ($x < $n) {
                if (! $matrix[$y][$x]) {
                    $x++;
                    continue;
                }
                // Merge horizontal runs into one rect to keep the SVG small.
                $run = 0;
                while ($x + $run < $n && $matrix[$y][$x + $run]) {
                    $run++;
                }
                $rects .= sprintf(
                    '<rect x="%d" y="%d" width="%d" height="1"/>',
                    $x + $quiet, $y + $quiet, $run
                );
                $x += $run;
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" '
            .'shape-rendering="crispEdges" role="img" aria-label="Two-factor QR code">'
            .'<rect width="%d" height="%d" fill="%s"/><g fill="%s">%s</g></svg>',
            $size, $size, $total, $total, $total, $total,
            htmlspecialchars($light, ENT_QUOTES), htmlspecialchars($dark, ENT_QUOTES), $rects
        );
    }

    /** Render as a data: URI, convenient for `<img src>`. */
    public static function dataUri(string $text, int $size = 200): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($text, $size));
    }

    /**
     * Build the final module matrix.
     *
     * @return array<int, array<int, bool>>
     */
    public static function matrix(string $text): array
    {
        $version = self::chooseVersion($text);
        $bits = self::encodeData($text, $version);
        $codewords = self::interleave($bits, $version);

        return self::place($codewords, $version);
    }

    private static function chooseVersion(string $text): int
    {
        $len = strlen($text);

        foreach (self::DATA_CODEWORDS_L as $version => $capacity) {
            // 4 bits mode + 8/16 bits length + payload, rounded up to bytes.
            $lengthBits = $version < 10 ? 8 : 16;
            $needed = (int) ceil((4 + $lengthBits + $len * 8) / 8);

            if ($needed <= $capacity) {
                return $version;
            }
        }

        throw new \InvalidArgumentException('Payload too long for supported QR versions.');
    }

    /** Byte-mode bitstream with terminator, padding and pad codewords. */
    private static function encodeData(string $text, int $version): string
    {
        $lengthBits = $version < 10 ? 8 : 16;

        $bits = '0100'; // byte mode
        $bits .= str_pad(decbin(strlen($text)), $lengthBits, '0', STR_PAD_LEFT);

        foreach (str_split($text) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }

        $capacityBits = self::DATA_CODEWORDS_L[$version] * 8;

        // Terminator (up to 4 zero bits)
        $bits .= str_repeat('0', min(4, max(0, $capacityBits - strlen($bits))));

        // Pad to a byte boundary
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        // Alternating pad codewords
        $pads = ['11101100', '00010001'];
        $i = 0;
        while (strlen($bits) < $capacityBits) {
            $bits .= $pads[$i++ % 2];
        }

        return $bits;
    }

    /** Split into blocks, append Reed–Solomon EC, then interleave. */
    private static function interleave(string $bits, int $version): array
    {
        [$ecPerBlock, $blockSizes] = self::EC_L[$version];

        $data = [];
        foreach (str_split($bits, 8) as $byte) {
            $data[] = bindec($byte);
        }

        $blocks = [];
        $ecBlocks = [];
        $offset = 0;

        foreach ($blockSizes as $size) {
            $block = array_slice($data, $offset, $size);
            $offset += $size;
            $blocks[] = $block;
            $ecBlocks[] = self::reedSolomon($block, $ecPerBlock);
        }

        $out = [];

        $maxData = max(array_map('count', $blocks));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }

        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }

        return $out;
    }

    // ── GF(256) arithmetic ──

    private static array $expTable = [];

    private static array $logTable = [];

    private static function initTables(): void
    {
        if (self::$expTable !== []) {
            return;
        }

        $x = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$expTable[$i] = $x;
            self::$logTable[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D; // QR generator polynomial
            }
        }
        for ($i = 256; $i < 512; $i++) {
            self::$expTable[$i] = self::$expTable[$i - 255];
        }
    }

    private static function mul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return self::$expTable[(self::$logTable[$a] + self::$logTable[$b]) % 255];
    }

    /** @return array<int,int> */
    private static function reedSolomon(array $data, int $ecLength): array
    {
        self::initTables();

        // Generator polynomial: ∏ (x - α^i), coefficients in descending degree.
        $gen = [1];
        for ($i = 0; $i < $ecLength; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef;                                   // × x
                $next[$j + 1] ^= self::mul($coef, self::$expTable[$i]); // × α^i
            }
            $gen = $next;
        }

        $remainder = array_merge($data, array_fill(0, $ecLength, 0));

        for ($i = 0; $i < count($data); $i++) {
            $factor = $remainder[$i];
            if ($factor === 0) {
                continue;
            }
            foreach ($gen as $j => $coef) {
                $remainder[$i + $j] ^= self::mul($coef, $factor);
            }
        }

        return array_slice($remainder, count($data), $ecLength);
    }

    // ── Module placement ──

    private static function place(array $codewords, int $version): array
    {
        $n = $version * 4 + 17;

        $m = array_fill(0, $n, array_fill(0, $n, false));   // module value
        $reserved = array_fill(0, $n, array_fill(0, $n, false)); // function pattern?

        // Finder patterns + separators
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$fx, $fy]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $px = $fx + $x;
                    $py = $fy + $y;
                    if ($px < 0 || $py < 0 || $px >= $n || $py >= $n) {
                        continue;
                    }
                    $inRing = ($x >= 0 && $x <= 6 && ($y === 0 || $y === 6))
                        || ($y >= 0 && $y <= 6 && ($x === 0 || $x === 6));
                    $inCore = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
                    $m[$py][$px] = $inRing || $inCore;
                    $reserved[$py][$px] = true;
                }
            }
        }

        // Timing patterns
        for ($i = 8; $i < $n - 8; $i++) {
            $on = $i % 2 === 0;
            $m[6][$i] = $on;
            $m[$i][6] = $on;
            $reserved[6][$i] = true;
            $reserved[$i][6] = true;
        }

        // Alignment patterns
        $centres = self::ALIGNMENT[$version] ?? [];
        foreach ($centres as $cy) {
            foreach ($centres as $cx) {
                // Skip the three that collide with finders.
                if (($cx <= 8 && $cy <= 8) || ($cx <= 8 && $cy >= $n - 9) || ($cx >= $n - 9 && $cy <= 8)) {
                    continue;
                }
                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $m[$cy + $y][$cx + $x] = max(abs($x), abs($y)) !== 1;
                        $reserved[$cy + $y][$cx + $x] = true;
                    }
                }
            }
        }

        // Dark module
        $m[$n - 8][8] = true;
        $reserved[$n - 8][8] = true;

        // Reserve format information areas
        for ($i = 0; $i < 9; $i++) {
            if (! $reserved[$i][8]) {
                $reserved[$i][8] = true;
            }
            if (! $reserved[8][$i]) {
                $reserved[8][$i] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$n - 1 - $i] = true;
            $reserved[$n - 1 - $i][8] = true;
        }

        // Reserve version information (version >= 7)
        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $reserved[$i][$n - 11 + $j] = true;
                    $reserved[$n - 11 + $j][$i] = true;
                }
            }
        }

        // Lay the data bits in the zig-zag order, applying mask 0.
        $bitStream = '';
        foreach ($codewords as $cw) {
            $bitStream .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }

        $pos = 0;
        $len = strlen($bitStream);
        $upward = true;

        for ($right = $n - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right = 5; // skip the vertical timing column
            }

            for ($v = 0; $v < $n; $v++) {
                $y = $upward ? $n - 1 - $v : $v;

                foreach ([0, 1] as $dx) {
                    $x = $right - $dx;

                    if ($reserved[$y][$x]) {
                        continue;
                    }

                    $bit = $pos < $len ? $bitStream[$pos] === '1' : false;
                    $pos++;

                    // Mask 0: (row + column) % 2 === 0
                    if (($y + $x) % 2 === 0) {
                        $bit = ! $bit;
                    }

                    $m[$y][$x] = $bit;
                }
            }

            $upward = ! $upward;
        }

        self::writeFormatInfo($m, $n);

        if ($version >= 7) {
            self::writeVersionInfo($m, $n, $version);
        }

        return $m;
    }

    /** Format info for EC level L with mask 0, BCH(15,5) protected. */
    private static function writeFormatInfo(array &$m, int $n): void
    {
        $data = 0b01000; // L (01) + mask 0 (000)

        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        for ($i = 0; $i < 15; $i++) {
            $bit = (bool) (($bits >> $i) & 1);

            // Copy 1 — around the top-left finder
            if ($i < 6) {
                $m[$i][8] = $bit;
            } elseif ($i === 6) {
                $m[7][8] = $bit;
            } elseif ($i === 7) {
                $m[8][8] = $bit;
            } elseif ($i === 8) {
                $m[8][7] = $bit;
            } else {
                $m[8][14 - $i] = $bit;
            }

            // Copy 2 — split across the other two finders
            if ($i < 8) {
                $m[8][$n - 1 - $i] = $bit;
            } else {
                $m[$n - 15 + $i][8] = $bit;
            }
        }
    }

    /** Version info block, BCH(18,6) protected. */
    private static function writeVersionInfo(array &$m, int $n, int $version): void
    {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
        }
        $bits = ($version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $bit = (bool) (($bits >> $i) & 1);
            $a = intdiv($i, 3);
            $b = $i % 3;

            $m[$a][$n - 11 + $b] = $bit;
            $m[$n - 11 + $b][$a] = $bit;
        }
    }
}
