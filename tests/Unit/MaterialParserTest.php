<?php

namespace Tests\Unit;

use App\Services\Learning\MaterialParserService;
use Tests\TestCase;

/**
 * Extraction correctness, focused on the failure that actually reached
 * production: a PDF whose text contained Windows-1252 bytes, which MySQL
 * rejected outright with "Incorrect string value".
 */
class MaterialParserTest extends TestCase
{
    private function parser(): MaterialParserService
    {
        return app(MaterialParserService::class);
    }

    /**
     * The regression. 0xAD is a soft hyphen — a legal single byte in
     * Windows-1252 and invalid on its own in UTF-8. PDF producers emit it
     * constantly.
     */
    public function test_windows_1252_bytes_are_converted_to_valid_utf8(): void
    {
        $raw = "About the HELM\xAD Project. Funded October 2002 \x96 September 2005."
            .str_repeat(' Additional body text to clear the minimum length gate.', 10);

        $this->assertFalse(mb_check_encoding($raw, 'UTF-8'), 'Fixture must start as invalid UTF-8.');

        $parsed = $this->parser()->parse($raw);

        $this->assertTrue(
            mb_check_encoding($parsed, 'UTF-8'),
            'Extracted text must be valid UTF-8 or the database insert fails.'
        );
    }

    public function test_soft_hyphens_are_stripped_rather_than_left_invisible(): void
    {
        $raw = "three\xADyear curriculum development project"
            .str_repeat(' padding to clear the minimum length gate.', 10);

        $parsed = $this->parser()->parse($raw);

        $this->assertStringContainsString('threeyear', $parsed);
        $this->assertStringNotContainsString("\u{00AD}", $parsed);
    }

    public function test_smart_punctuation_is_normalised(): void
    {
        $raw = "Student\u{2019}s Guide \u{2013} the \u{201C}best\u{201D} one\u{00A0}here"
            .str_repeat(' padding to clear the minimum length gate.', 10);

        $parsed = $this->parser()->parse($raw);

        $this->assertStringContainsString("Student's Guide - the \"best\" one here", $parsed);
    }

    public function test_a_byte_order_mark_is_removed(): void
    {
        $raw = "\u{FEFF}Chapter one begins here."
            .str_repeat(' padding to clear the minimum length gate.', 10);

        $this->assertStringStartsWith('Chapter one', $this->parser()->parse($raw));
    }

    /**
     * A whole textbook should not put half a megabyte on every row. The AI
     * only ever reads the first few thousand characters anyway.
     */
    public function test_very_long_documents_are_capped(): void
    {
        config(['ai.max_extractable_chars' => 5000]);

        $parsed = $this->parser()->parse(str_repeat('a very long document. ', 5000));

        $this->assertSame(5000, mb_strlen($parsed));
    }

    public function test_text_shorter_than_the_minimum_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->parser()->parse('Too short.');
    }

    public function test_windows_line_endings_are_normalised(): void
    {
        $raw = "line one\r\nline two\rline three"
            .str_repeat("\npadding to clear the minimum length gate.", 10);

        $parsed = $this->parser()->parse($raw);

        $this->assertStringNotContainsString("\r", $parsed);
    }

    public function test_binary_content_is_recognised_as_unreadable(): void
    {
        // A compressed image stream — what you get from a scanned PDF — is
        // dominated by low control bytes.
        //
        // Note this deliberately does NOT use random_bytes(): uniformly random
        // data is ~75% printable-or-high by byte value and so passes the
        // readability ratio every time. Real binary is not uniformly random.
        $binary = str_repeat(implode('', array_map('chr', range(0, 31))), 20);

        $this->assertFalse($this->parser()->isReadableText($binary));
    }

    public function test_ordinary_prose_is_recognised_as_readable(): void
    {
        $this->assertTrue($this->parser()->isReadableText('A pronoun stands in for a person or thing.'));
    }

    public function test_non_latin_scripts_are_treated_as_readable(): void
    {
        // High bytes are UTF-8 continuation bytes, not binary noise.
        $this->assertTrue($this->parser()->isReadableText('Yorùbá èdè kíkọ́ àti ìkẹ́kọ̀ọ́'));
    }
}
