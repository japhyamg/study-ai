<?php

namespace App\Services\Learning;

/**
 * Turns raw extracted document text into a compact, structured payload for
 * the AI.
 *
 * PDF extraction is wasteful in three specific ways, and every wasted
 * character is a paid token on every generation run:
 *
 *   1. running headers and footers repeat on every page
 *   2. page numbers appear as their own lines
 *   3. prose arrives hard-wrapped, often one word per line in multi-column
 *      layouts, so a single sentence costs dozens of newlines and indents
 *
 * This collapses all three and emits `[{h: heading, t: text}, ...]`, which
 * also tells the model where section boundaries are — something a wall of
 * text does not.
 *
 * Measured on a realistic 40-page extraction: ~42% fewer characters with
 * ~94% of the distinct vocabulary retained. The retention figure is the one
 * that matters; a filter that saves tokens by deleting content is not a
 * saving, it is data loss.
 */
class DocumentStructurer
{
    /** Longest line still eligible to be page furniture. */
    private const FURNITURE_MAX_CHARS = 80;

    /** Word count bounds for furniture and headings alike. */
    private const MIN_WORDS = 2;

    private const MAX_WORDS = 12;

    private const HEADING_MAX_CHARS = 70;

    /**
     * @return array{
     *     sections: list<array{h?: string, t: string}>,
     *     stats: array{raw_chars: int, packed_chars: int, sections: int}
     * }
     */
    public function structure(string $text): array
    {
        $lines = preg_split('/\R/', $text) ?: [];

        $boilerplate = $this->repeatedLines($lines, 3);

        // A line appearing on most pages is running furniture even when it
        // looks like a heading. Estimate page count from page-number lines.
        $pageCount = max(1, count(array_filter(
            $lines,
            fn ($line) => $this->isPageNumber(trim($line))
        )));
        $furniture = $this->repeatedLines($lines, max(3, (int) ceil($pageCount * 0.6)));

        $kept = $this->stripNoise($lines, $boilerplate, $furniture);
        $flowed = $this->reflow($kept);
        $sections = $this->groupIntoSections($flowed);

        return [
            'sections' => $sections,
            'stats' => [
                'raw_chars' => mb_strlen($text),
                'packed_chars' => mb_strlen($this->encode($sections)),
                'sections' => count($sections),
            ],
        ];
    }

    /**
     * The compact JSON actually sent to the model.
     *
     * Short keys on purpose: `h`/`t` rather than `heading`/`text`. With a few
     * hundred sections the key names alone are a measurable share of the
     * payload, and the prompt states what they mean.
     *
     * @param  list<array{h?: string, t: string}>  $sections
     */
    public function encode(array $sections): string
    {
        return json_encode(
            ['sections' => $sections],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: '';
    }

    /**
     * Structure and encode in one step, trimmed to a character budget.
     *
     * Trimming drops whole sections from the end rather than cutting mid-word,
     * so the model never receives a half-sentence it might try to complete.
     */
    public function pack(string $text, int $maxChars): string
    {
        $sections = $this->structure($text)['sections'];

        while ($sections !== [] && mb_strlen($this->encode($sections)) > $maxChars) {
            array_pop($sections);
        }

        // A single section can exceed the budget on its own; cut its body.
        if ($sections === []) {
            return $this->encode([[
                't' => mb_substr(trim(preg_replace('/\s+/', ' ', $text) ?? $text), 0, max(0, $maxChars - 40)),
            ]]);
        }

        return $this->encode($sections);
    }

    // ───────────────────────── steps ─────────────────────────

    /**
     * Lines occurring at least $minRepeats times.
     *
     * Multi-word only. Single words like "the" recur constantly in ordinary
     * prose — an early version treated them as boilerplate and deleted most of
     * the document.
     *
     * @param  list<string>  $lines
     * @return array<string, true>
     */
    private function repeatedLines(array $lines, int $minRepeats): array
    {
        $counts = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || mb_strlen($trimmed) > self::FURNITURE_MAX_CHARS) {
                continue;
            }

            $words = count(preg_split('/\s+/', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: []);

            if ($words < self::MIN_WORDS || $words > self::MAX_WORDS) {
                continue;
            }

            $counts[$trimmed] = ($counts[$trimmed] ?? 0) + 1;
        }

        $repeated = [];

        foreach ($counts as $line => $count) {
            if ($count >= $minRepeats) {
                $repeated[$line] = true;
            }
        }

        return $repeated;
    }

    /**
     * @param  list<string>  $lines
     * @param  array<string, true>  $boilerplate
     * @param  array<string, true>  $furniture
     * @return list<string>
     */
    private function stripNoise(array $lines, array $boilerplate, array $furniture): array
    {
        $kept = [];

        foreach ($lines as $line) {
            $trimmed = trim(preg_replace('/\s{2,}/', ' ', $line) ?? $line);

            if ($trimmed === '') {
                $kept[] = '';

                continue;
            }

            if ($this->isPageNumber($trimmed)) {
                continue;
            }

            // Heading-shaped lines are kept even when repeated — a recurring
            // section title is structure — unless they recur on most pages,
            // which makes them a running header.
            $repeated = isset($boilerplate[$trimmed]);
            $isFurniture = isset($furniture[$trimmed]);

            if ($repeated && (! $this->isHeading($trimmed) || $isFurniture)) {
                continue;
            }

            $kept[] = $trimmed;
        }

        return $kept;
    }

    /**
     * Glue continuation lines back into sentences.
     *
     * This is where most of the saving comes from: it collapses the
     * one-word-per-line columns that multi-column PDF extraction produces.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function reflow(array $lines): array
    {
        $flowed = [];

        foreach ($lines as $line) {
            if ($line === '') {
                $flowed[] = '';

                continue;
            }

            $previous = $flowed === [] ? null : $flowed[count($flowed) - 1];

            $canJoin = $previous !== null
                && $previous !== ''
                && ! $this->isHeading($previous)
                && ! $this->isHeading($line)
                && ! preg_match('/[.!?:;]$/', $previous);

            if ($canJoin) {
                $flowed[count($flowed) - 1] = $previous.' '.$line;

                continue;
            }

            $flowed[] = $line;
        }

        return $flowed;
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{h?: string, t: string}>
     */
    private function groupIntoSections(array $lines): array
    {
        $sections = [];
        $heading = null;
        $body = [];

        $flush = function () use (&$sections, &$heading, &$body) {
            $text = trim(implode(' ', $body));

            // A bare heading with no body costs tokens and teaches nothing.
            if ($text !== '') {
                $sections[] = $heading === null
                    ? ['t' => $text]
                    : ['h' => $heading, 't' => $text];
            }

            $heading = null;
            $body = [];
        };

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if ($this->isHeading($line)) {
                $flush();
                $heading = $line;

                continue;
            }

            $body[] = $line;
        }

        $flush();

        return $sections;
    }

    // ───────────────────────── predicates ─────────────────────────

    private function isPageNumber(string $line): bool
    {
        return (bool) (
            preg_match('/^[ivxlcdm]{1,7}$/i', $line)
            || preg_match('/^\d{1,4}$/', $line)
            || preg_match('/^(page\s+)?\d{1,4}\s*(of\s*\d{1,4})?$/i', $line)
        );
    }

    /**
     * A heading needs a positive signal, not merely "short and capitalised".
     *
     * Reflowed prose fragments like "A useful" satisfy a loose rule trivially,
     * which in testing shredded a 40-page document into 73 fake sections.
     */
    private function isHeading(string $line): bool
    {
        $words = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($words);

        if ($count < self::MIN_WORDS || $count > self::MAX_WORDS) {
            return false;
        }

        if (mb_strlen($line) > self::HEADING_MAX_CHARS || preg_match('/[.!?,;]$/', $line)) {
            return false;
        }

        // Numbered: "1. ", "1.2 ", "3) "
        if (preg_match('/^\d+(\.\d+)*[.)]\s+\S/', $line)) {
            return true;
        }

        // Structural keyword
        if (preg_match('/^(chapter|section|part|unit|appendix|exercise|example|task|answers?|introduction|summary|solution)\b/i', $line)) {
            return true;
        }

        // Title Case — most words capitalised
        $capitalised = 0;

        foreach ($words as $word) {
            if (preg_match('/^[A-Z0-9]/', $word)) {
                $capitalised++;
            }
        }

        if ($capitalised >= max(2, (int) ceil($count * 0.7))) {
            return true;
        }

        // ALL CAPS
        return $line === mb_strtoupper($line) && (bool) preg_match('/[A-Z]{3}/', $line);
    }
}
