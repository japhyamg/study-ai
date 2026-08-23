<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The AI-written study guide for a material.
 *
 * `sections` is a list of {heading, body} so the UI can render a contents
 * list and deep-link to a section; `content` keeps the whole thing as one
 * markdown blob for printing and export.
 */
class StudyGuide extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['material_id', 'title', 'summary', 'content', 'sections', 'key_terms'];

    protected $casts = [
        'sections' => 'array',
        'key_terms' => 'array',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Normalised sections. Tolerates the two shapes models return —
     * {heading, body} and {heading, content} — plus the legacy blob where
     * everything lived in `content` as JSON.
     *
     * @return list<array{heading: string, body: string}>
     */
    public function normalisedSections(): array
    {
        $sections = $this->sections;

        if (! is_array($sections) || $sections === []) {
            $sections = $this->legacySections();
        }

        $out = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $heading = trim((string) ($section['heading'] ?? $section['title'] ?? ''));
            $body = $section['body'] ?? $section['content'] ?? '';

            // A model occasionally nests a list under `body`; flatten it
            // rather than rendering "Array".
            if (is_array($body)) {
                $body = implode("\n", array_map(
                    static fn ($line) => is_scalar($line) ? (string) $line : '',
                    $body
                ));
            }

            $body = trim((string) $body);

            if ($heading === '' && $body === '') {
                continue;
            }

            $out[] = ['heading' => $heading ?: 'Section', 'body' => $body];
        }

        return $out;
    }

    /** Pull sections out of the pre-Phase-2 JSON blob in `content`. */
    private function legacySections(): array
    {
        $decoded = json_decode((string) $this->content, true);

        return is_array($decoded) && isset($decoded['sections']) && is_array($decoded['sections'])
            ? $decoded['sections']
            : [];
    }

    /** @return list<array{term: string, definition: string}> */
    public function normalisedKeyTerms(): array
    {
        $out = [];

        foreach ((array) $this->key_terms as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $term = trim((string) ($entry['term'] ?? ''));
            $definition = trim((string) ($entry['definition'] ?? ''));

            if ($term !== '' && $definition !== '') {
                $out[] = ['term' => $term, 'definition' => $definition];
            }
        }

        return $out;
    }

    public function displayTitle(): string
    {
        return $this->title ?: ($this->material?->title ?? 'Study guide');
    }
}
