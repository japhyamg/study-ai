<?php

namespace Tests\Unit;

use App\Exceptions\AiServiceException;
use Tests\TestCase;

/**
 * A provider error body is not fit to show a user.
 *
 * A real 401 reads {"message":"Wrong API Key","code":"wrong_api_key"} — it
 * means nothing to a teacher, and it advertises which provider is in use and
 * that its credential is misconfigured. Anyone able to trigger a generation
 * could otherwise probe the integration through its error text.
 */
class AiServiceExceptionTest extends TestCase
{
    /** The exact body from the bug report. */
    private const WRONG_KEY_BODY = '{"message":"Wrong API Key","type":"invalid_request_error","param":"api_key","code":"wrong_api_key"}';

    public function test_the_provider_body_never_reaches_the_public_message(): void
    {
        $e = AiServiceException::fromHttp(401, self::WRONG_KEY_BODY);

        $this->assertStringNotContainsString('API Key', $e->publicMessage());
        $this->assertStringNotContainsString('api_key', $e->publicMessage());
        $this->assertStringNotContainsString('401', $e->publicMessage());
        $this->assertStringNotContainsString('{', $e->publicMessage());
    }

    public function test_the_provider_body_is_kept_for_the_log(): void
    {
        $e = AiServiceException::fromHttp(401, self::WRONG_KEY_BODY);

        $this->assertStringContainsString('Wrong API Key', $e->privateDetail());
        $this->assertStringContainsString('401', $e->privateDetail());
    }

    public function test_a_bad_key_is_reported_as_a_configuration_problem(): void
    {
        $e = AiServiceException::fromHttp(401, self::WRONG_KEY_BODY);

        $this->assertTrue($e->isActionable());
        $this->assertStringContainsString('administrator', $e->publicMessage());
    }

    /**
     * The distinction a reader acts on is whether waiting will help. A bad key
     * never fixes itself; a 429 usually does.
     */
    public function test_a_rate_limit_is_reported_as_temporary(): void
    {
        $e = AiServiceException::fromHttp(429, 'slow down');

        $this->assertFalse($e->isActionable());
        $this->assertStringContainsString('try again', mb_strtolower($e->publicMessage()));
    }

    public function test_a_server_error_is_reported_as_temporary(): void
    {
        foreach ([500, 502, 503] as $status) {
            $e = AiServiceException::fromHttp($status, 'upstream exploded');

            $this->assertStringNotContainsString('exploded', $e->publicMessage());
            $this->assertStringContainsString('temporarily unavailable', $e->publicMessage());
        }
    }

    public function test_an_out_of_credit_response_tells_an_admin_to_top_up(): void
    {
        $e = AiServiceException::fromHttp(402, '{"error":"insufficient_quota"}');

        $this->assertTrue($e->isActionable());
        $this->assertStringNotContainsString('insufficient_quota', $e->publicMessage());
    }

    public function test_every_failure_carries_a_reference_linking_page_to_log(): void
    {
        $e = AiServiceException::fromHttp(401, self::WRONG_KEY_BODY);

        $this->assertMatchesRegularExpression('/^[0-9A-F]{6}$/', $e->reference());
    }

    public function test_references_differ_between_failures(): void
    {
        $first = AiServiceException::fromHttp(500, 'a')->reference();
        $second = AiServiceException::fromHttp(500, 'b')->reference();

        $this->assertNotSame($first, $second);
    }

    public function test_an_unmapped_status_still_produces_a_safe_message(): void
    {
        $e = AiServiceException::fromHttp(418, 'I am a teapot with secrets');

        $this->assertStringNotContainsString('teapot', $e->publicMessage());
        $this->assertStringNotContainsString('secrets', $e->publicMessage());
    }

    public function test_private_detail_falls_back_to_the_public_message(): void
    {
        $e = new AiServiceException('Something plain happened.');

        $this->assertSame('Something plain happened.', $e->privateDetail());
    }
}
