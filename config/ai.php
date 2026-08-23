<?php

return [

    /*
    |───────────────────────────────────────────────────────────────────────
    | Generation budgets
    |───────────────────────────────────────────────────────────────────────
    | Per-generation-type token ceilings. A study guide needs far more room
    | than a topic-link lookup, and asking for 8k tokens on every call is how
    | you get a surprise invoice. Truncated JSON is the usual symptom of these
    | being too low — AiService repairs what it can, but raising the ceiling
    | for the offending type is the real fix.
    */

    'max_tokens' => [
        'study_guide' => 4096,
        'study_guide_overview' => 1536,
        'flashcards' => 2048,
        'questions' => 3072,
        'quiz' => 3072,
        'topics' => 2048,
        'topic_links' => 1024,
        'key_terms' => 1536,
        'default' => 2048,
    ],

    /*
    | How much source material to send. Models degrade and costs climb long
    | before the context window is actually full, so we cut deliberately.
    */

    'input_limits' => [
        'default' => 30000,
        'per_document' => 15000,
        'study_guide_section' => 6000,
        'overview' => 4000,
    ],

    /*
    | Defaults for teacher-facing generation forms.
    */

    'defaults' => [
        'flashcard_count' => 15,
        'question_count' => 10,
        'question_types' => ['multiple-choice'],
        'difficulty' => 'medium',
    ],

    /*
    |───────────────────────────────────────────────────────────────────────
    | Retry behaviour
    |───────────────────────────────────────────────────────────────────────
    | Rate limits are the common failure. Linear backoff (5s, 10s, 15s) is
    | what the upstream providers actually recommend for 429s; exponential
    | backoff just makes a queued job sit there longer for no benefit.
    */

    'retry' => [
        'attempts' => 3,
        'base_delay_seconds' => 5,
    ],

    'timeout' => env('AI_TIMEOUT', 120),

    'cache_ttl_days' => env('AI_CACHE_TTL_DAYS', 30),

    /*
    |───────────────────────────────────────────────────────────────────────
    | Uploads
    |───────────────────────────────────────────────────────────────────────
    */

    'uploads' => [
        'max_size_kb' => 20480,
        'disk' => env('MATERIAL_DISK', 'local'),
        'path' => 'materials',
        'accepted' => ['pdf', 'docx', 'doc', 'txt', 'md', 'csv'],
    ],

    /*
    | Minimum extracted characters worth sending to a model. Below this we
    | assume extraction failed (scanned PDF, image-only deck) and tell the
    | teacher rather than burning tokens on three words of header text.
    */

    'min_extractable_chars' => 200,

];
