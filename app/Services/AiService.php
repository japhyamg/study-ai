<?php

namespace App\Services;

use App\Models\AiCache;
use App\Models\AiProvider;
use App\Models\TokenUsage;
use App\Support\JsonRepair;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OpenAI-compatible AI client, ported from src/lib/ai/groq.ts.
 * Reads the active AiProvider (baseUrl / apiKey / model), sanitizes JSON,
 * tracks token usage, and caches responses.
 */
class AiService
{
    private const MAX_TOKENS = 4096;
    private const MAX_RETRIES = 3;
    private const CONTENT_LIMIT = 5000;
    private const CACHE_TTL_DAYS = 30;

    private const SYSTEM_PROMPT = 'You are an expert educational content creator. Source material arrives as compact JSON — an array of sections, each with an optional heading "h" and its text "t". Treat it as one continuous document and use the headings to understand its structure. Return ONLY valid JSON — no markdown, no backticks, no extra text. For math/science: use proper Unicode symbols (≤, ≥, ≠, ≈, ±, ∞, →, ∈, ⊂, ∪, ∩, ∫, ∑, √, ∂, ∇, Δ, θ, λ, μ, π, σ, ω, α, β, γ, ², ³, ⁰, ₁, ₂, ₃, ⁴, ⁵, ⁶, ⁷, ⁸, ⁹). Write fractions as a/b, exponents as x², derivatives as dy/dx, integrals as ∫f(x)dx. Show step-by-step solutions with numbered steps. Include units (m/s², mol⁻¹).';

    /**
     * @param  array  $context  ['userId'=>?, 'schoolId'=>?]
     * @param  string  $generationType  selects the token ceiling in config/ai.php
     * @return mixed decoded JSON (array/object)
     *
     * @throws Throwable
     */
    public function completeJson(string $prompt, array $context = [], ?string $cacheKey = null, string $generationType = 'default')
    {
        $cacheKey = $cacheKey ?? md5($prompt);

        // Cache hit
        if ($cached = AiCache::where('content_hash', $cacheKey)->where('expires_at', '>', now())->first()) {
            return $cached->response;
        }

        $result = $this->callWithRetry($prompt, $context, $generationType);

        AiCache::updateOrCreate(
            ['content_hash' => $cacheKey],
            ['response' => $result, 'expires_at' => now()->addDays($this->cacheTtlDays())]
        );

        return $result;
    }

    private function cacheTtlDays(): int
    {
        return (int) config('ai.cache_ttl_days', self::CACHE_TTL_DAYS);
    }

    /** Token ceiling for a generation type, from config/ai.php. */
    private function maxTokensFor(string $generationType): int
    {
        return (int) config(
            "ai.max_tokens.{$generationType}",
            config('ai.max_tokens.default', self::MAX_TOKENS)
        );
    }

    /** How much source text to send for a given purpose. */
    private function inputLimit(string $key = 'default'): int
    {
        return (int) config("ai.input_limits.{$key}", config('ai.input_limits.default', self::CONTENT_LIMIT));
    }

    /**
     * Generate study content (flashcards | questions | study-guide) and return parsed JSON.
     */
    public function generateStudyContent(string $content, string $type, array $options = [], array $context = []): mixed
    {
        $truncated = $this->truncate($content, $this->inputLimit());
        $questionCount = $options['questionCount'] ?? 10;
        $questionTypes = $options['questionTypes'] ?? ['multiple-choice'];
        $typesStr = implode(', ', $questionTypes);

        if ($type === 'study-guide') {
            return $this->generateStudyGuide($truncated, $context);
        }

        $prompts = [
            'flashcards' => "Extract 15-20 flashcards from this content. Each needs a clear question (front) and concise answer (back).

For math/science: include formulas, theorems, definitions, and problem-solving methods. Use proper Unicode symbols (≤, ≥, ∫, ∑, √, ±, ∞, →, ², ³, ₀, ₁, ₂, ₃). Show key steps on the back.

Return ONLY a JSON array of objects with keys: front, back, tags.
Example: [{\"front\":\"What is the quadratic formula?\",\"back\":\"x = (-b ± √(b²-4ac)) / 2a\",\"tags\":[\"algebra\",\"quadratic\"]}]

SOURCE (JSON: sections[] with h = heading, t = text):
$truncated",

            'questions' => "Create $questionCount $typesStr questions from this content. Each needs 4 options (A-D), one correct answer (correctIdx: 0-3), and a brief explanation.

For math/science: show step-by-step solutions in explanations. Use proper Unicode symbols. Make distractors plausible (common mistakes).

CRITICAL RULES:
- Return ONLY a valid JSON array, no markdown, no extra text
- Start with [ and end with ]
- All string values must be on ONE LINE — no literal newlines inside strings
- options must always be a JSON array of strings, never an object
- Generate questions that are specific to the content, not generic

Return ONLY a JSON array of objects with keys: question, options, correctIdx, explanation, difficulty, tags.
Example: [{\"question\":\"Solve: x squared plus 5x plus 6 equals 0\",\"options\":[\"x equals negative 2 and x equals negative 3\",\"x equals 2 and x equals 3\",\"x equals negative 1 and x equals negative 6\",\"x equals 1 and x equals 6\"],\"correctIdx\":0,\"explanation\":\"Step 1: Factor into x plus 2 times x plus 3 equals 0. Step 2: x equals negative 2 or x equals negative 3.\",\"difficulty\":2,\"tags\":[\"algebra\"]}]

SOURCE (JSON: sections[] with h = heading, t = text):
$truncated",
        ];

        $generationType = $type === 'questions' ? 'questions' : 'flashcards';

        return $this->completeJson(
            $prompts[$type] ?? $prompts['flashcards'],
            $context,
            md5($type.':'.$truncated),
            $generationType
        );
    }

    public function generateTopics(string $topic, array $context = []): mixed
    {
        $prompt = "Generate 8-12 study topics for: \"$topic\"

For math/science: include problem types, key formulas, solution methods, and prerequisite concepts.

Return ONLY a JSON array of objects with keys: topic, description, keyConcepts, difficulty.
Example: [{\"topic\":\"Quadratic Equations\",\"description\":\"Solving and analyzing second-degree polynomial equations\",\"keyConcepts\":[\"Quadratic formula\",\"Factoring\",\"Discriminant\"],\"difficulty\":2}]";

        return $this->completeJson($prompt, $context, md5('topics:'.$topic), 'topics');
    }

    /**
     * Multi-section study guide (batched), ported from generateStudyGuide().
     */
    public function generateStudyGuide(string $content, array $context = []): array
    {
        $snippet = $this->truncate($content, $this->inputLimit('study_guide_section'));

        $overview = $this->completeJson(
            "You are creating a study guide for this content.

Return ONLY this exact JSON object (no array, no markdown):
{
  \"title\": \"specific topic title here\",
  \"summary\": \"2-3 sentence overview of the topic\",
  \"basics\": [
    {\"point\": \"prerequisite concept 1\", \"why\": \"why it matters\"},
    {\"point\": \"prerequisite concept 2\", \"why\": \"why it matters\"},
    {\"point\": \"prerequisite concept 3\", \"why\": \"why it matters\"}
  ],
  \"relatedTopics\": [\"related topic 1\", \"related topic 2\", \"related topic 3\"]
}

Rules:
- title must be specific to the content, not generic
- basics should be 3-5 things students must already know
- relatedTopics should be 3-5 connected topics
- No math symbols in strings, write as words
- All strings on ONE LINE

SOURCE (JSON: sections[] with h = heading, t = text):
" . $this->truncate($snippet, $this->inputLimit('overview')),
            $context,
            md5('sg-overview:'.$content),
            'study_guide_overview'
        );

        $title = $overview['title'] ?? 'Study Guide';
        $summary = $overview['summary'] ?? '';
        $basics = $overview['basics'] ?? [];
        $relatedTopics = $overview['relatedTopics'] ?? [];

        $sectionDefs = [
            ['heading' => 'Key Concepts', 'instruction' => 'The main ideas and principles, explained clearly in plain text'],
            ['heading' => 'Key Terms and Definitions', 'instruction' => 'Important vocabulary with clear definitions'],
            ['heading' => 'Detailed Notes', 'instruction' => 'In-depth explanation of each subtopic'],
            ['heading' => 'Common Misconceptions', 'instruction' => 'Things students get wrong and the correct understanding'],
            ['heading' => 'Examples and Applications', 'instruction' => 'Worked examples or real-world applications'],
            ['heading' => 'Exam Tips', 'instruction' => 'What to focus on, key things to memorise, common question types'],
            ['heading' => 'Summary', 'instruction' => 'The most important points as a concise revision block'],
        ];

        $allSections = [];
        $batches = [array_slice($sectionDefs, 0, 4), array_slice($sectionDefs, 4)];

        foreach ($batches as $batch) {
            $sectionList = '';
            foreach ($batch as $i => $s) {
                $sectionList .= "Section " . ($i + 1) . ": \"{$s['heading']}\" — {$s['instruction']}\n";
            }
            $prompt = "Generate exactly " . count($batch) . " study guide sections for this content.

Sections to generate:
$sectionList

Return ONLY a JSON array of exactly " . count($batch) . " objects:
[
  {
    \"heading\": \"exact section name from above\",
    \"content\": \"detailed content as a single plain text string\"
  }
]

RULES:
- Return a JSON array, NOT an object
- Each section needs at least 4-6 sentences of real content
- content must be a plain string — no nested objects or arrays
- For bullet points use: \"• point one\\n• point two\\n• point three\"
- No math symbols: write \"x squared\" not x^2, \"plus or minus\" not +/-
- All strings on ONE LINE (use \\n for line breaks, never actual newlines)
- Be specific to the actual content provided, not generic

SOURCE (JSON: sections[] with h = heading, t = text):
" . $this->truncate($snippet, $this->inputLimit('study_guide_section'));

            $batchResult = $this->completeJson(
                $prompt,
                $context,
                md5('sg-batch:'.$content.':'.implode(',', array_column($batch, 'heading'))),
                'study_guide'
            );

            $batchSections = [];
            if (is_array($batchResult)) {
                $batchSections = array_values($batchResult);
            } elseif (isset($batchResult['sections']) && is_array($batchResult['sections'])) {
                $batchSections = $batchResult['sections'];
            } elseif (isset($batchResult['heading'])) {
                $batchSections = [$batchResult];
            }

            foreach ($batchSections as $s) {
                if (is_array($s) && isset($s['heading'], $s['content'])) {
                    $allSections[] = $s;
                }
            }
        }

        // Key terms
        $keyTerms = [];
        try {
            $termsResult = $this->completeJson(
                "Extract the key terms and definitions from this content.

Return ONLY a JSON array:
[
  {\"term\": \"term name\", \"definition\": \"clear definition in plain text\"}
]

Rules:
- 5-12 terms maximum
- definitions must be plain text, no symbols
- No math symbols: write words instead
- All strings on ONE LINE

SOURCE (JSON: sections[] with h = heading, t = text):
" . $this->truncate($snippet, $this->inputLimit('overview')),
                $context,
                md5('sg-terms:'.$content),
                'key_terms'
            );
            if (is_array($termsResult)) {
                foreach ($termsResult as $t) {
                    if (is_array($t) && isset($t['term'], $t['definition'])) {
                        $keyTerms[] = $t;
                    }
                }
            } elseif (isset($termsResult['keyTerms']) && is_array($termsResult['keyTerms'])) {
                $keyTerms = $termsResult['keyTerms'];
            }
        } catch (Throwable $e) {
            // key terms optional
        }

        return [
            'title' => $title,
            'summary' => $summary,
            'basics' => $basics,
            'relatedTopics' => $relatedTopics,
            'sections' => $allSections,
            'keyTerms' => $keyTerms,
        ];
    }

    // ── internal ──

    /**
     * Issue the request, retrying on rate limits and transient upstream errors.
     *
     * Backoff is linear (5s, 10s, 15s) rather than exponential: these calls
     * already run inside a queued job, and providers publish linear guidance
     * for 429s. Doubling just parks the worker for longer with no better
     * success rate.
     */
    private function callWithRetry(string $prompt, array $context, string $generationType = 'default'): mixed
    {
        $attempts = max(1, (int) config('ai.retry.attempts', self::MAX_RETRIES));
        $baseDelay = max(1, (int) config('ai.retry.base_delay_seconds', 5));
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->dispatchCall($prompt, $context, $generationType);
            } catch (TokenLimitError $e) {
                // A budget breach is a business rule, not a transient fault.
                throw $e;
            } catch (Throwable $e) {
                $lastError = $e;

                if (! $this->isRetryable($e) || $attempt >= $attempts) {
                    throw $e;
                }

                $wait = $baseDelay * $attempt;

                Log::warning('AI call failed, retrying', [
                    'attempt' => $attempt,
                    'of' => $attempts,
                    'wait_seconds' => $wait,
                    'type' => $generationType,
                    'error' => $e->getMessage(),
                ]);

                sleep($wait);
            }
        }

        throw $lastError ?? new \RuntimeException('AI call failed.');
    }

    /** A single request/response round trip. */
    private function dispatchCall(string $prompt, array $context, string $generationType): mixed
    {
        $provider = $this->getActiveProvider();

        if (! $provider) {
            throw new \RuntimeException('No active AI provider is configured. Add one under Super Admin → AI providers.');
        }

        // Enforce the teacher's monthly budget before spending anything.
        app(TokenLimitService::class)->assertTeacherTokenBudget($context['userId'] ?? null);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$provider->api_key,
            'Content-Type' => 'application/json',
        ])
            ->timeout((int) config('ai.timeout', 120))
            ->post(rtrim($provider->base_url, '/').'/chat/completions', [
                'model' => $provider->model,
                'temperature' => 0.7,
                'max_tokens' => $this->maxTokensFor($generationType),
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('AI HTTP error: '.$response->status().' '.$response->body());
        }

        $json = $response->json();

        if ($usage = $json['usage'] ?? null) {
            $this->trackTokenUsage(
                (int) ($usage['prompt_tokens'] ?? 0),
                (int) ($usage['completion_tokens'] ?? 0),
                $provider->model,
                'generate',
                $context
            );
        }

        $text = $json['choices'][0]['message']['content'] ?? '';

        if (trim($text) === '') {
            throw new \RuntimeException('The AI returned an empty response. Try again.');
        }

        return $this->parseJson($text, $generationType);
    }

    /** Cut source text to a budget, telling the model it was cut. */
    private function truncate(string $text, int $maxChars): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars)."\n\n... [source truncated for length]";
    }

    /**
     * Ask which existing topics are prerequisites of / related to / follow-ups
     * from a new one.
     *
     * Only names are sent — the model never sees ids, so its answers are
     * matched back by name and anything it invented is discarded by the caller.
     *
     * @param  list<string>  $existingTopicNames
     * @return list<array{name: string, relationship_type: string, confidence_score: float}>
     */
    public function suggestTopicLinks(string $topicName, array $existingTopicNames, array $context = []): array
    {
        if ($existingTopicNames === []) {
            return [];
        }

        $names = json_encode(array_values($existingTopicNames), JSON_UNESCAPED_SLASHES);

        $prompt = <<<PROMPT
        You are an educational topic linker. A new topic "{$topicName}" is being added to a syllabus.

        Existing topics: {$names}

        Identify which of the existing topics are prerequisites for, related to, or follow-ups from "{$topicName}".

        Rules:
        - Only use names exactly as they appear in the existing topics list
        - relationship_type must be one of: prerequisite, related, follow_up
        - confidence_score is a number between 0 and 1
        - Omit topics with no genuine relationship — an empty array is a valid answer
        - Return ONLY a JSON array, no markdown

        Example: [{"name":"Algebra","relationship_type":"prerequisite","confidence_score":0.95}]
        PROMPT;

        $result = $this->completeJson(
            $prompt,
            $context,
            md5('topic-links:'.$topicName.':'.$names),
            'topic_links'
        );

        $rows = is_array($result) ? ($result['links'] ?? $result) : [];
        $valid = [];

        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['name'])) {
                continue;
            }

            $type = $row['relationship_type'] ?? 'related';

            $valid[] = [
                'name' => (string) $row['name'],
                'relationship_type' => in_array($type, \App\Models\TopicLink::TYPES, true) ? $type : 'related',
                'confidence_score' => max(0.0, min(1.0, (float) ($row['confidence_score'] ?? 0.5))),
            ];
        }

        return $valid;
    }

    private function getActiveProvider(): ?AiProvider
    {
        return AiProvider::where('is_active', true)->first();
    }

    private function trackTokenUsage(int $promptTokens, int $completionTokens, string $model, string $operation, array $context): void
    {
        try {
            TokenUsage::create([
                'operation' => $operation,
                'model' => $model,
                'user_id' => $context['userId'] ?? null,
                'school_id' => $context['schoolId'] ?? null,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
                'cost' => ($promptTokens * 0.00008 + $completionTokens * 0.00008) / 1000,
            ]);
        } catch (Throwable $e) {
            // token usage tracking is best-effort
        }
    }

    private function isRetryable(Throwable $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, '429') || str_contains($msg, 'rate_limit') || str_contains($msg, 'rate limit')
            || str_contains($msg, 'quota') || str_contains($msg, '503') || str_contains($msg, '502') || str_contains($msg, '500');
    }

    /**
     * Parse a model response into JSON, escalating through repair strategies.
     *
     * Order matters: each step is cheaper and less lossy than the next.
     *   1. strip markdown fences the model added anyway
     *   2. strip control bytes that json_decode rejects outright
     *   3. straight decode
     *   4. drop trailing commas
     *   5. transliterate unicode maths that breaks string escaping
     *   6. close a document truncated by max_tokens
     *   7. extract the first balanced object/array from surrounding prose
     */
    public function parseJson(string $text, string $generationType = 'default'): mixed
    {
        $text = JsonRepair::stripControlBytes(JsonRepair::stripFences($text));

        if (trim($text) === '') {
            throw new \RuntimeException('The AI returned an empty response.');
        }

        $decoded = json_decode($text, true, 512, JSON_INVALID_UTF8_IGNORE);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Trailing commas — by far the most common single defect.
        $noTrailingCommas = preg_replace('/,\s*([}\]])/', '$1', $text) ?? $text;
        $decoded = json_decode($noTrailingCommas, true, 512, JSON_INVALID_UTF8_IGNORE);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Unicode maths inside strings.
        $transliterated = $this->escapeControlChars($noTrailingCommas);
        $decoded = json_decode($transliterated, true, 512, JSON_INVALID_UTF8_IGNORE);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Ran out of tokens mid-write: salvage the complete prefix.
        if ($repaired = JsonRepair::repair($transliterated)) {
            Log::warning('AI returned truncated JSON; repaired', [
                'type' => $generationType,
                'recovered_keys' => is_array($repaired) ? count($repaired) : 0,
            ]);

            return $repaired;
        }

        // Wrapped in prose despite instructions: pull out the first balanced value.
        if ($extracted = $this->extractJson($transliterated)) {
            $decoded = json_decode($extracted, true, 512, JSON_INVALID_UTF8_IGNORE);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        Log::error('AI returned unparseable JSON', [
            'type' => $generationType,
            'error' => json_last_error_msg(),
            'head' => mb_substr($text, 0, 400),
        ]);

        throw new \RuntimeException(
            'The AI returned a response that could not be read. Try generating again — if it keeps happening, reduce the amount of source text.'
        );
    }

    private function escapeControlChars(string $text): string
    {
        $replacements = [
            "\u{2019}" => "'", "\u{2018}" => "'",
            "\u{201C}" => '"', "\u{201D}" => '"',
            "\u{2013}" => '-', "\u{2014}" => '-',
            "\u{00B2}" => '^2', "\u{00B3}" => '^3',
            "\u{00BD}" => '1/2', "\u{00BC}" => '1/4',
            "\u{2212}" => '-', "\u{00D7}" => 'x', "\u{00F7}" => '/',
            "\u{0394}" => 'delta', "\u{03B1}" => 'alpha',
            "\u{03B2}" => 'beta', "\u{03B3}" => 'gamma',
            "\u{03BB}" => 'lambda', "\u{03BC}" => 'mu',
            "\u{03C0}" => 'pi', "\u{03C3}" => 'sigma',
            "\u{03B8}" => 'theta', "\u{03C9}" => 'omega',
            "\u{221E}" => 'infinity', "\u{2248}" => 'approx',
            "\u{2260}" => '!=', "\u{2264}" => '<=', "\u{2265}" => '>=',
            "\u{221A}" => 'sqrt', "\u{2211}" => 'sum',
            "\u{222B}" => 'integral', "\u{2192}" => '->',
            "\u{21CC}" => '<->', "\u{00B1}" => '+/-',
            "\u{00B0}" => ' degrees',
            "\u{2080}" => '0', "\u{2081}" => '1', "\u{2082}" => '2',
            "\u{2083}" => '3', "\u{2084}" => '4',
        ];

        $out = '';
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $cp = mb_ord($char, 'UTF-8');
            if ($char === '\\') {
                $out .= $char;
                if ($i + 1 < $len) {
                    $out .= mb_substr($text, ++$i, 1, 'UTF-8');
                }
                continue;
            }
            if ($char === '"') {
                $out .= $char;
                continue;
            }
            // inside string? simple heuristic: track quotes (handled by previous)
            if ($cp < 0x20) {
                if ($char === "\n") { $out .= '\\n'; continue; }
                if ($char === "\r") { $out .= '\\r'; continue; }
                if ($char === "\t") { $out .= '\\t'; continue; }
                continue;
            }
            if (isset($replacements[$char])) {
                $out .= $replacements[$char];
                continue;
            }
            $out .= $char;
        }
        return $out;
    }

    private function extractJson(string $text): ?string
    {
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $ch = $text[$i];
            if ($ch !== '[' && $ch !== '{') {
                continue;
            }
            $open = $ch;
            $close = $ch === '[' ? ']' : '}';
            $depth = 0;
            $inString = false;
            $escape = false;
            for ($j = $i; $j < $len; $j++) {
                $c = $text[$j];
                if ($escape) { $escape = false; continue; }
                if ($c === '\\') { $escape = true; continue; }
                if ($c === '"' && !$escape) { $inString = !$inString; continue; }
                if ($inString) { continue; }
                if ($c === $open) { $depth++; }
                if ($c === $close) {
                    $depth--;
                    if ($depth === 0) {
                        return substr($text, $i, $j - $i + 1);
                    }
                }
            }
        }
        return null;
    }
}
