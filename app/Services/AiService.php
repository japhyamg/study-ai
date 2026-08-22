<?php

namespace App\Services;

use App\Models\AiCache;
use App\Models\AiProvider;
use App\Models\TokenUsage;
use Illuminate\Support\Facades\Http;
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

    private const SYSTEM_PROMPT = 'You are an expert educational content creator. Return ONLY valid JSON — no markdown, no backticks, no extra text. For math/science: use proper Unicode symbols (≤, ≥, ≠, ≈, ±, ∞, →, ∈, ⊂, ∪, ∩, ∫, ∑, √, ∂, ∇, Δ, θ, λ, μ, π, σ, ω, α, β, γ, ², ³, ⁰, ₁, ₂, ₃, ⁴, ⁵, ⁶, ⁷, ⁸, ⁹). Write fractions as a/b, exponents as x², derivatives as dy/dx, integrals as ∫f(x)dx. Show step-by-step solutions with numbered steps. Include units (m/s², mol⁻¹).';

    /**
     * @param array $context ['userId'=>?, 'schoolId'=>?]
     * @return mixed decoded JSON (array/object)
     * @throws Throwable
     */
    public function completeJson(string $prompt, array $context = [], ?string $cacheKey = null)
    {
        $cacheKey = $cacheKey ?? md5($prompt);

        // Cache hit
        if ($cached = AiCache::where('content_hash', $cacheKey)->where('expires_at', '>', now())->first()) {
            return $cached->response;
        }

        $result = $this->callWithRetry($prompt, $context);

        AiCache::updateOrCreate(
            ['content_hash' => $cacheKey],
            ['response' => $result, 'expires_at' => now()->addDays(self::CACHE_TTL_DAYS)]
        );

        return $result;
    }

    /**
     * Generate study content (flashcards | questions | study-guide) and return parsed JSON.
     */
    public function generateStudyContent(string $content, string $type, array $options = [], array $context = []): mixed
    {
        $truncated = mb_substr($content, 0, self::CONTENT_LIMIT);
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

Content:
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

Content:
$truncated",
        ];

        return $this->completeJson($prompts[$type] ?? $prompts['flashcards'], $context, md5($type . ':' . $truncated));
    }

    public function generateTopics(string $topic, array $context = []): mixed
    {
        $prompt = "Generate 8-12 study topics for: \"$topic\"

For math/science: include problem types, key formulas, solution methods, and prerequisite concepts.

Return ONLY a JSON array of objects with keys: topic, description, keyConcepts, difficulty.
Example: [{\"topic\":\"Quadratic Equations\",\"description\":\"Solving and analyzing second-degree polynomial equations\",\"keyConcepts\":[\"Quadratic formula\",\"Factoring\",\"Discriminant\"],\"difficulty\":2}]";

        return $this->completeJson($prompt, $context, md5('topics:' . $topic));
    }

    /**
     * Multi-section study guide (batched), ported from generateStudyGuide().
     */
    public function generateStudyGuide(string $content, array $context = []): array
    {
        $snippet = mb_substr($content, 0, 5000);

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

Content:
" . mb_substr($snippet, 0, 2000),
            $context,
            md5('sg-overview:' . $content)
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

Content:
" . mb_substr($snippet, 0, 2500);

            $batchResult = $this->completeJson($prompt, $context, md5('sg-batch:' . $content . ':' . implode(',', array_column($batch, 'heading'))));

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

Content:
" . mb_substr($snippet, 0, 2000),
                $context,
                md5('sg-terms:' . $content)
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

    private function callWithRetry(string $prompt, array $context): mixed
    {
        $lastError = null;
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $provider = $this->getActiveProvider();
                if (!$provider) {
                    throw new \RuntimeException('No active AI provider configured.');
                }

                // enforce token budget
                app(TokenLimitService::class)->assertTeacherTokenBudget($context['userId'] ?? null);

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $provider->api_key,
                    'Content-Type' => 'application/json',
                ])->timeout(120)->post(rtrim($provider->base_url, '/') . '/chat/completions', [
                    'model' => $provider->model,
                    'temperature' => 0.7,
                    'max_tokens' => self::MAX_TOKENS,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

                if ($response->failed()) {
                    throw new \RuntimeException('AI HTTP error: ' . $response->status() . ' ' . $response->body());
                }

                $json = $response->json();
                $usage = $json['usage'] ?? null;
                if ($usage) {
                    $this->trackTokenUsage(
                        (int) ($usage['prompt_tokens'] ?? 0),
                        (int) ($usage['completion_tokens'] ?? 0),
                        $provider->model,
                        'generate',
                        $context
                    );
                }

                $text = $json['choices'][0]['message']['content'] ?? '';
                return $this->parseJson($text);
            } catch (TokenLimitError $e) {
                throw $e;
            } catch (Throwable $e) {
                $lastError = $e;
                if ($this->isRetryable($e) && $attempt < self::MAX_RETRIES) {
                    $delay = min(5000 * (2 ** ($attempt - 1)), 30000);
                    usleep($delay * 1000);
                    continue;
                }
                throw $e;
            }
        }
        throw $lastError ?? new \RuntimeException('AI call failed.');
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
     * Sanitize + robustly parse JSON from an LLM response, ported from groq.ts.
     */
    public function parseJson(string $text): mixed
    {
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/```$/m', '', $text);
        $text = trim($text);

        // sanitize unicode/math that breaks JSON (ported escapeMathInJsonStrings)
        $text = $this->escapeControlChars($text);

        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // remove trailing commas
            $cleaned = preg_replace('/,\s*([}\]])/', '$1', $text);
            try {
                return json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e2) {
                $extracted = $this->extractJson($cleaned);
                if ($extracted !== null) {
                    return json_decode($extracted, true);
                }
                throw new \RuntimeException('Failed to parse AI response as JSON: ' . $e->getMessage());
            }
        }
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
