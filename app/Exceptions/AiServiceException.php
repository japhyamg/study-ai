<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * An AI failure carrying two messages: one for the log, one for the user.
 *
 * Provider errors are not safe to show. A 401 body reads
 * `{"message":"Wrong API Key",...}` — that is meaningless to a teacher, and
 * it leaks which provider is in use and how it is configured. Anyone who can
 * trigger a generation could probe the integration through its error text.
 *
 * So the raw text goes to the log with a short reference, and the user sees
 * plain language plus that reference to quote to support.
 */
class AiServiceException extends RuntimeException
{
    /** Fault is ours/the provider's, not something the user can fix. */
    public const KIND_PROVIDER = 'provider';

    /** The user (or their admin) can do something about it. */
    public const KIND_ACTIONABLE = 'actionable';

    public function __construct(
        string $publicMessage,
        private string $privateDetail = '',
        private string $kind = self::KIND_PROVIDER,
        private ?string $reference = null,
        ?Throwable $previous = null,
        private ?int $status = null,
        private ?int $retryAfter = null,
    ) {
        parent::__construct($publicMessage, 0, $previous);

        $this->reference ??= strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    /** Safe to render. Never contains provider output. */
    public function publicMessage(): string
    {
        return $this->getMessage();
    }

    /** For the log only. */
    public function privateDetail(): string
    {
        return $this->privateDetail !== '' ? $this->privateDetail : $this->getMessage();
    }

    /** Short code shown to the user and logged, so the two can be matched. */
    public function reference(): string
    {
        return (string) $this->reference;
    }

    public function isActionable(): bool
    {
        return $this->kind === self::KIND_ACTIONABLE;
    }

    /** HTTP status, when this came from a provider response. */
    public function status(): ?int
    {
        return $this->status;
    }

    /**
     * Whether trying the same request again could succeed.
     *
     * Carried as data rather than inferred from the message: the public text
     * is deliberately free of status codes, so string matching on it — which
     * is what the retry logic used to do — silently stopped working.
     */
    public function isRetryable(): bool
    {
        return in_array($this->status, [408, 425, 429, 500, 502, 503, 504], true);
    }

    /** Provider-supplied Retry-After, in seconds, when it gave one. */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Build from a raw provider/transport failure, choosing a public message
     * from the HTTP status.
     *
     * The distinction that matters to the reader is whether waiting will help.
     * A bad key never fixes itself; a 429 or a 503 usually does.
     */
    public static function fromHttp(int $status, string $body): self
    {
        [$message, $kind] = match (true) {
            $status === 401 || $status === 403 => [
                'AI generation is not configured correctly. An administrator needs to check the AI provider settings.',
                self::KIND_ACTIONABLE,
            ],
            $status === 402 => [
                'The AI provider account has run out of credit. An administrator needs to top it up.',
                self::KIND_ACTIONABLE,
            ],
            $status === 429 => [
                'The AI service is busy right now. Wait a minute and try again.',
                self::KIND_PROVIDER,
            ],
            $status === 408 || $status >= 500 => [
                'The AI service is temporarily unavailable. Try again in a few minutes.',
                self::KIND_PROVIDER,
            ],
            default => [
                'The AI service could not complete this request. Try again.',
                self::KIND_PROVIDER,
            ],
        };

        return new self(
            $message,
            'HTTP '.$status.' '.$body,
            $kind,
            null,
            null,
            $status,
            self::retryAfterFrom($body),
        );
    }

    /**
     * Pull a retry hint out of the provider's error body.
     *
     * OpenRouter nests it as metadata.retry_after_seconds; others put it at
     * the top level. Waiting the interval the provider actually asked for
     * beats a fixed backoff that may be shorter than their window.
     */
    private static function retryAfterFrom(string $body): ?int
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return null;
        }

        $candidates = [
            $decoded['retry_after_seconds'] ?? null,
            $decoded['error']['metadata']['retry_after_seconds'] ?? null,
            $decoded['error']['metadata']['headers']['Retry-After'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                // Cap it: a provider asking for ten minutes should fail fast
                // rather than hold a worker.
                return min((int) $candidate, 60);
            }
        }

        return null;
    }
}
