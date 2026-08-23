<?php

namespace Tests\Unit;

use App\Exceptions\AiServiceException;
use App\Support\JsonRepair;
use Tests\TestCase;

/**
 * Recovery of imperfect model output.
 *
 * A real generation failed with "Unparseable JSON after repair: No error" on
 * output that was plainly recoverable — one complete flashcard followed by a
 * trailing comma. Two separate defects produced that:
 *
 *   1. escapeControlChars() escaped EVERY control character, including the
 *      newlines between tokens. That turned the whole document into one
 *      invalid literal before JsonRepair ever saw it.
 *   2. it ran before the repair step, so the salvage worked on the corrupted
 *      copy rather than on what the model actually wrote.
 *
 * The "No error" was a third, smaller bug: json_last_error() is global and
 * every json_decode resets it, so the log read a stale value.
 */
class AiJsonRecoveryTest extends TestCase
{
    /** Exactly the shape from the incident. */
    private const TRUNCATED_FLASHCARDS = <<<'JSON'
[
  {
    "front": "What is the BODMAS rule for the order of arithmetic operations?",
    "back": "First priority: evaluate terms within Brackets. Second: Of, Division, Multiplication. Third: Addition and Subtraction.",
    "tags": ["algebra", "BODMAS", "order-of-operations"]
  },
JSON;

    // ───────────────────────── the incident ─────────────────────────

    public function test_a_truncated_flashcard_array_is_recovered(): void
    {
        $repaired = JsonRepair::repair(self::TRUNCATED_FLASHCARDS);

        $this->assertIsArray($repaired);
        $this->assertCount(1, $repaired);
        $this->assertStringContainsString('BODMAS', $repaired[0]['front']);
        $this->assertSame(['algebra', 'BODMAS', 'order-of-operations'], $repaired[0]['tags']);
    }

    public function test_recovery_survives_the_full_pipeline_order(): void
    {
        // Mirrors parseJson: strip fences, strip control bytes, drop trailing
        // commas, then repair. Repair must come before transliteration.
        $text = JsonRepair::stripControlBytes(JsonRepair::stripFences(self::TRUNCATED_FLASHCARDS));
        $text = preg_replace('/,\s*([}\]])/', '$1', $text) ?? $text;

        $this->assertIsArray(JsonRepair::repair($text));
    }

    // ───────────────── structure must survive escaping ─────────────────

    /**
     * The core defect. A JSON document is mostly newlines between tokens;
     * escaping those makes it unparseable.
     */
    public function test_structural_newlines_are_not_escaped(): void
    {
        $service = app(\App\Services\AiService::class);
        $method = new \ReflectionMethod($service, 'escapeControlChars');

        $valid = "{\n  \"a\": 1,\n  \"b\": [2, 3]\n}";
        $result = $method->invoke($service, $valid);

        $this->assertStringNotContainsString('\\n', $result, 'Newlines between tokens must stay literal.');
        $this->assertIsArray(json_decode($result, true));
    }

    /** But a raw newline inside a string is genuinely invalid and must be escaped. */
    public function test_newlines_inside_strings_are_escaped(): void
    {
        $service = app(\App\Services\AiService::class);
        $method = new \ReflectionMethod($service, 'escapeControlChars');

        $result = $method->invoke($service, "{\"text\": \"line one\nline two\"}");
        $decoded = json_decode($result, true);

        $this->assertIsArray($decoded);
        $this->assertSame("line one\nline two", $decoded['text']);
    }

    public function test_maths_symbols_are_transliterated_inside_strings(): void
    {
        $service = app(\App\Services\AiService::class);
        $method = new \ReflectionMethod($service, 'escapeControlChars');

        $result = $method->invoke($service, '{"q": "is 5 ≤ 7 and 3 × 2?"}');
        $decoded = json_decode($result, true);

        $this->assertIsArray($decoded);
        $this->assertStringContainsString('<=', $decoded['q']);
        $this->assertStringContainsString('3 x 2', $decoded['q']);
    }

    public function test_pre_escaped_sequences_are_left_alone(): void
    {
        $service = app(\App\Services\AiService::class);
        $method = new \ReflectionMethod($service, 'escapeControlChars');

        $result = $method->invoke($service, '{"s": "a \"quoted\" word", "n": 2}');
        $decoded = json_decode($result, true);

        $this->assertIsArray($decoded, 'Escaped quotes must not flip string tracking.');
        $this->assertSame(2, $decoded['n']);
    }

    // ───────────────────────── retry classification ─────────────────────────

    /**
     * Retryability is carried as data. It used to be inferred by searching the
     * message for "429" — which stopped working the moment those messages were
     * rewritten for users, silently disabling retries.
     */
    public function test_a_rate_limit_is_retryable(): void
    {
        $this->assertTrue(AiServiceException::fromHttp(429, '{}')->isRetryable());
        $this->assertTrue(AiServiceException::fromHttp(503, '{}')->isRetryable());
        $this->assertTrue(AiServiceException::fromHttp(500, '{}')->isRetryable());
    }

    public function test_a_bad_key_is_not_retryable(): void
    {
        $this->assertFalse(AiServiceException::fromHttp(401, '{}')->isRetryable());
        $this->assertFalse(AiServiceException::fromHttp(402, '{}')->isRetryable());
    }

    public function test_the_public_message_carries_no_status_code_to_match_on(): void
    {
        $e = AiServiceException::fromHttp(429, '{}');

        $this->assertStringNotContainsString('429', $e->publicMessage());
        $this->assertTrue($e->isRetryable(), 'Classification must not depend on the wording.');
    }

    /** The provider's own hint, from the body in the incident log. */
    public function test_retry_after_is_read_from_the_provider_body(): void
    {
        $body = '{"error":{"code":429,"metadata":{"retry_after_seconds":5,"headers":{"Retry-After":"5"}}}}';

        $this->assertSame(5, AiServiceException::fromHttp(429, $body)->retryAfter());
    }

    public function test_an_absent_retry_hint_is_null(): void
    {
        $this->assertNull(AiServiceException::fromHttp(429, 'not json')->retryAfter());
    }

    /** A provider asking for ten minutes should not hold a worker that long. */
    public function test_an_excessive_retry_hint_is_capped(): void
    {
        $body = '{"retry_after_seconds": 600}';

        $this->assertSame(60, AiServiceException::fromHttp(429, $body)->retryAfter());
    }
}
