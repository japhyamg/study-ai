<?php

namespace Tests\Unit;

use App\Services\Learning\DocumentStructurer;
use Tests\TestCase;

/**
 * Structuring exists to cut tokens, so the tests measure two things together:
 * how much smaller the payload gets, and how much of the original vocabulary
 * survives.
 *
 * The second matters more. A filter that "saves" 90% by deleting the document
 * is not a saving, and an early version of this did exactly that — single
 * words like "the" repeat constantly in prose, were classified as running
 * boilerplate, and most of the text disappeared.
 */
class DocumentStructurerTest extends TestCase
{
    private function structurer(): DocumentStructurer
    {
        return new DocumentStructurer;
    }

    /** A 40-page extraction: running header/footer, page numbers, hard wrapping. */
    private function textbookExtraction(): string
    {
        $paragraphs = [
            'A knowledge of the properties of numbers is fundamental to the study of engineering mathematics.',
            'A useful way of picturing numbers is to use a number line drawn from left to right.',
            'To perform calculations with numbers we use addition, subtraction, multiplication and division.',
        ];

        $headings = [
            '1. Numbers, operations and common notations',
            'The Number Line',
            '2. Calculation With Numbers',
        ];

        $lines = [];

        for ($page = 1; $page <= 30; $page++) {
            // running header, identical on every page
            $lines[] = 'HELM (2015):';
            $lines[] = 'Section 1.1: Mathematical Notation and Symbols';
            $lines[] = '';

            $lines[] = $headings[$page % 3].' '.$page;
            $lines[] = '';

            // prose hard-wrapped one word per line, as multi-column PDFs emit
            foreach (explode(' ', $paragraphs[$page % 3]) as $word) {
                $lines[] = '  '.$word;
            }

            $lines[] = '';
            $lines[] = (string) $page;                 // page number
            $lines[] = '  Workbook 1: Basic Algebra';  // running footer
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /** @return list<string> distinct words of 3+ letters */
    private function vocabulary(string $text): array
    {
        preg_match_all('/[a-z]{3,}/', mb_strtolower($text), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    // ───────────────────────── the point of the feature ─────────────────────────

    public function test_structuring_substantially_reduces_the_payload(): void
    {
        $raw = $this->textbookExtraction();
        $packed = $this->structurer()->pack($raw, 100000);

        $this->assertLessThan(
            mb_strlen($raw) * 0.75,
            mb_strlen($packed),
            'Structuring should cut at least a quarter of the characters.'
        );
    }

    /**
     * The guard that would have caught the bug that deleted the document.
     *
     * Measured against the body prose only: words that exist *solely* in the
     * running header and footer are supposed to disappear, so counting them
     * would penalise the filter for doing its job.
     */
    public function test_structuring_preserves_the_body_vocabulary(): void
    {
        $body = 'A knowledge of the properties of numbers is fundamental to the study of engineering mathematics. '
            .'A useful way of picturing numbers is to use a number line drawn from left to right. '
            .'To perform calculations with numbers we use addition, subtraction, multiplication and division.';

        $packed = $this->structurer()->pack($this->textbookExtraction(), 100000);

        $before = $this->vocabulary($body);
        $after = $this->vocabulary($packed);
        $kept = count(array_intersect($before, $after)) / max(1, count($before));

        $this->assertGreaterThan(
            0.95,
            $kept,
            'Body prose must survive — a saving that deletes content is data loss, not a saving.'
        );
    }

    /** Conversely, the furniture really should be gone. */
    public function test_words_unique_to_page_furniture_are_removed(): void
    {
        $packed = $this->structurer()->pack($this->textbookExtraction(), 100000);

        $this->assertStringNotContainsString('Workbook', $packed);
        $this->assertStringNotContainsString('HELM', $packed);
    }

    public function test_running_headers_and_footers_are_removed(): void
    {
        $packed = $this->structurer()->pack($this->textbookExtraction(), 100000);

        $this->assertSame(
            1,
            max(1, substr_count($packed, 'Workbook 1: Basic Algebra')),
            'A footer repeated on 30 pages should not appear 30 times.'
        );
        $this->assertLessThan(3, substr_count($packed, 'HELM (2015)'));
    }

    public function test_hard_wrapped_lines_are_rejoined_into_sentences(): void
    {
        $raw = "Introduction To Algebra\n\nA\n  useful\n  way\n  of\n  picturing\n  numbers\n  is\n  to\n  use\n  a\n  line.\n";

        $packed = $this->structurer()->pack($raw, 100000);

        $this->assertStringContainsString('A useful way of picturing numbers is to use a line.', $packed);
    }

    // ───────────────────────── structure ─────────────────────────

    public function test_headings_are_captured_as_section_boundaries(): void
    {
        $raw = "1. Introduction\n\nThis section explains the basics of the topic in detail.\n\n"
            ."2. Method\n\nThis section explains the procedure that should be followed.\n";

        $result = $this->structurer()->structure($raw);

        $this->assertCount(2, $result['sections']);
        $this->assertSame('1. Introduction', $result['sections'][0]['h']);
        $this->assertSame('2. Method', $result['sections'][1]['h']);
    }

    /**
     * A loose heading rule ("short and capitalised") turned reflowed prose
     * fragments like "A useful" into headings and shredded a document into
     * dozens of fake sections.
     */
    public function test_prose_fragments_are_not_mistaken_for_headings(): void
    {
        $raw = "A Real Heading Here\n\nA useful way of picturing numbers is to use a number line, "
            ."which extends indefinitely in both directions.\n";

        $result = $this->structurer()->structure($raw);

        $this->assertCount(1, $result['sections']);
        $this->assertSame('A Real Heading Here', $result['sections'][0]['h']);
    }

    public function test_page_numbers_are_dropped(): void
    {
        $raw = "Some Real Heading\n\nBody text that should survive intact here.\n\n42\n\niv\n\nPage 7 of 20\n";

        $packed = $this->structurer()->pack($raw, 100000);

        $this->assertStringContainsString('Body text that should survive', $packed);
        $this->assertStringNotContainsString('Page 7 of 20', $packed);
    }

    public function test_a_heading_with_no_body_is_dropped(): void
    {
        $result = $this->structurer()->structure("Lonely Heading Here\n");

        $this->assertSame([], $result['sections']);
    }

    // ───────────────────────── encoding and budget ─────────────────────────

    public function test_the_payload_is_valid_compact_json(): void
    {
        $packed = $this->structurer()->pack($this->textbookExtraction(), 100000);
        $decoded = json_decode($packed, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('sections', $decoded);
        $this->assertNotEmpty($decoded['sections']);
        $this->assertArrayHasKey('t', $decoded['sections'][0]);
    }

    public function test_packing_respects_the_character_budget(): void
    {
        $packed = $this->structurer()->pack($this->textbookExtraction(), 2000);

        $this->assertLessThanOrEqual(2000, mb_strlen($packed));
        $this->assertIsArray(json_decode($packed, true), 'Trimming must not produce invalid JSON.');
    }

    /** Trimming drops whole sections, so the model never sees a half sentence. */
    public function test_trimming_removes_whole_sections(): void
    {
        $packed = $this->structurer()->pack($this->textbookExtraction(), 3000);
        $decoded = json_decode($packed, true);

        foreach ($decoded['sections'] as $section) {
            $this->assertNotSame('', trim($section['t']));
        }
    }

    public function test_empty_input_produces_no_sections(): void
    {
        $this->assertSame([], $this->structurer()->structure('')['sections']);
    }

    public function test_short_plain_text_survives_intact(): void
    {
        $raw = 'Photosynthesis is the process by which plants convert light energy into chemical energy.';

        $this->assertStringContainsString('Photosynthesis is the process', $this->structurer()->pack($raw, 10000));
    }
}
