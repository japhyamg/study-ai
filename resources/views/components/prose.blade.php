@props(['text' => ''])

@php
    /**
     * Render AI-written text with light formatting.
     *
     * Everything is HTML-escaped first, then a small whitelist is applied —
     * bold, italic, inline code, bullet and numbered lists, arrows. The
     * content originates from a language model working on teacher uploads, so
     * it is never trusted as HTML; a full markdown renderer here would be an
     * XSS hole for the sake of formatting we do not need.
     */
    $render = function (string $raw): string {
        $inline = function (string $line): string {
            $out = e($line);

            // **bold** then *italic* — bold first so ** is not eaten by *.
            $out = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $out) ?? $out;
            $out = preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/s', '<em>$1</em>', $out) ?? $out;
            $out = preg_replace('/`(.+?)`/s', '<code class="rounded bg-surface-sunk px-1 py-0.5 text-[0.8125em]">$1</code>', $out) ?? $out;

            // ASCII arrows read better as real ones.
            return str_replace(['-&gt;', '=&gt;'], ['→', '⇒'], $out);
        };

        $html = '';
        $list = null; // 'ul' | 'ol' | null

        $closeList = function () use (&$html, &$list): void {
            if ($list) {
                $html .= "</{$list}>";
                $list = null;
            }
        };

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $closeList();

                continue;
            }

            // Bullet: -, *, • or ·
            if (preg_match('/^[-*•·]\s+(.*)$/u', $trimmed, $m)) {
                if ($list !== 'ul') {
                    $closeList();
                    $html .= '<ul class="ms-4 list-disc space-y-1">';
                    $list = 'ul';
                }

                $html .= '<li>'.$inline($m[1]).'</li>';

                continue;
            }

            // Numbered: 1. or 1)
            if (preg_match('/^\d+[.)]\s+(.*)$/', $trimmed, $m)) {
                if ($list !== 'ol') {
                    $closeList();
                    $html .= '<ol class="ms-4 list-decimal space-y-1">';
                    $list = 'ol';
                }

                $html .= '<li>'.$inline($m[1]).'</li>';

                continue;
            }

            // Sub-heading inside a section body.
            if (preg_match('/^#{2,6}\s+(.*)$/', $trimmed, $m)) {
                $closeList();
                $html .= '<p class="mt-3 font-medium text-ink">'.$inline($m[1]).'</p>';

                continue;
            }

            $closeList();
            $html .= '<p>'.$inline($trimmed).'</p>';
        }

        $closeList();

        return $html;
    };
@endphp

<div {{ $attributes->merge(['class' => 'prose-body space-y-2 text-sm leading-relaxed text-muted']) }}>
    {!! $render((string) $text) !!}
</div>
