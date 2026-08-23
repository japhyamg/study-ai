<?php

namespace App\Services\Learning;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

/**
 * Turns an uploaded file into plain text for the AI pipeline.
 *
 * The important rule here is that this service never returns binary garbage.
 * A scanned PDF has no text layer, and feeding its decompressed stream bytes
 * to a model wastes a teacher's whole token budget producing nonsense. Every
 * path is gated by {@see isReadableText()}, and failures raise a message
 * written for a teacher, not a stack trace.
 */
class MaterialParserService
{
    /** Below this ratio of printable characters we treat the text as binary. */
    private const READABLE_RATIO = 0.60;

    /**
     * @throws RuntimeException with a message safe to show the user
     */
    public function parse(UploadedFile|string|null $input): string
    {
        if (is_string($input)) {
            return $this->capLength($this->cleanText($input));
        }

        if ($input === null) {
            throw new RuntimeException('No material provided. Upload a file or paste the text.');
        }

        return $this->parseFile(
            (string) $input->getRealPath(),
            $this->detectType($input)
        );
    }

    /**
     * Extract text from a file already on disk.
     *
     * Uploaded documents are stored, not transcribed into the database, so the
     * text has to be recoverable from the stored copy at generation time. This
     * is that path: same pipeline, no UploadedFile required.
     *
     * @param  string  $path  absolute path to a readable file
     * @param  string|null  $type  pdf|docx|txt|image; sniffed from the bytes when omitted
     *
     * @throws RuntimeException with a message safe to show the user
     */
    public function parseFile(string $path, ?string $type = null): string
    {
        if (! is_readable($path)) {
            throw new RuntimeException('The uploaded file could not be found. Re-upload it and try again.');
        }

        $type ??= $this->detectTypeFromPath($path);

        $text = match ($type) {
            'pdf' => $this->parsePdf($path),
            'docx' => $this->parseDocx($path),
            'image' => throw new RuntimeException(
                'Images cannot be read yet. Paste the text from the image, or upload a PDF or Word file.'
            ),
            // A ZIP that is not a .docx — a .pptx or .xlsx, most likely.
            // Falling through to the plain-text reader would report a
            // confusing encoding error instead of the real problem.
            'archive' => throw new RuntimeException(
                'That file type cannot be read. Upload a PDF, Word document or plain text file.'
            ),
            default => $this->parsePlainText($path),
        };

        if (! $this->isReadableText($text)) {
            throw new RuntimeException(
                'No readable text could be extracted. The file may be a scan or use an unsupported encoding — paste the text directly instead.'
            );
        }

        $text = $this->cleanText($text);

        if (mb_strlen($text) < config('ai.min_extractable_chars', 200)) {
            throw new RuntimeException(
                'Only a few characters could be extracted, which is not enough to generate study content. If this is a scanned document, paste the text directly.'
            );
        }

        return $this->capLength($text);
    }

    /**
     * Cap stored text. Whole textbooks do get uploaded; there is no value in
     * carrying half a megabyte of appendices on every row when generation only
     * ever reads the first few thousand characters.
     */
    private function capLength(string $text): string
    {
        $max = (int) config('ai.max_extractable_chars', 500000);

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
    }

    /**
     * Identify the file from its leading bytes first.
     *
     * Extensions and client-supplied MIME types are both trivially wrong —
     * a renamed .doc, a browser sending application/octet-stream. Magic bytes
     * are not.
     */
    public function detectType(UploadedFile $file): string
    {
        $header = $this->readHeader((string) $file->getRealPath(), 8);

        if (str_starts_with($header, '%PDF')) {
            return 'pdf';
        }

        $extension = strtolower($file->getClientOriginalExtension());

        // OOXML files are ZIP archives; the extension distinguishes docx from
        // xlsx/pptx, which we cannot read.
        if (str_starts_with($header, 'PK')) {
            return $extension === 'docx' ? 'docx' : 'archive';
        }

        $mime = (string) $file->getMimeType();

        return match (true) {
            $mime === 'application/pdf' || $extension === 'pdf' => 'pdf',
            str_contains($mime, 'word'), str_contains($mime, 'officedocument'), in_array($extension, ['docx', 'doc'], true) => 'docx',
            str_contains($mime, 'image'), in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true) => 'image',
            default => 'txt',
        };
    }

    /**
     * Same detection for a file on disk, where there is no client-supplied
     * MIME type or original filename to consult — only the bytes and the
     * stored extension.
     */
    public function detectTypeFromPath(string $path): string
    {
        $header = $this->readHeader($path, 8);

        if (str_starts_with($header, '%PDF')) {
            return 'pdf';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (str_starts_with($header, 'PK')) {
            return $extension === 'docx' ? 'docx' : 'archive';
        }

        return match (true) {
            $extension === 'pdf' => 'pdf',
            in_array($extension, ['docx', 'doc'], true) => 'docx',
            in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true) => 'image',
            default => 'txt',
        };
    }

    private function readHeader(string $path, int $bytes): string
    {
        if ($path === '' || ! is_readable($path)) {
            return '';
        }

        $handle = @fopen($path, 'rb');

        if (! $handle) {
            return '';
        }

        $header = (string) fread($handle, $bytes);
        fclose($handle);

        return $header;
    }

    // ───────────────────────── PDF ─────────────────────────

    private function parsePdf(string $path): string
    {
        // pdftotext handles layout, ligatures and encodings far better than
        // anything we can do in PHP. Use it when the host provides it.
        $text = $this->tryPdfToText($path);

        if ($text !== null && $this->isReadableText($text) && strlen(trim($text)) > 50) {
            return $text;
        }

        $text = $this->extractPdfTextInPhp($path);

        if (strlen(trim($text)) > 20 && $this->isReadableText($text)) {
            return $text;
        }

        throw new RuntimeException(
            'No selectable text was found in this PDF. It is most likely a scan — export a text-based PDF or paste the text directly.'
        );
    }

    private function tryPdfToText(string $path): ?string
    {
        if ($path === '' || ! function_exists('shell_exec')) {
            return null;
        }

        $disabled = (string) ini_get('disable_functions');

        if (str_contains($disabled, 'shell_exec')) {
            return null;
        }

        $output = @shell_exec('pdftotext -layout '.escapeshellarg($path).' - 2>/dev/null');

        return ($output !== null && trim($output) !== '') ? trim($output) : null;
    }

    /**
     * Minimal pure-PHP PDF text extraction: walk the objects, inflate the
     * content streams, and pull the text-showing operators out of them.
     *
     * This handles ordinary text-layer PDFs. It does not attempt CMap or
     * CID font decoding — the readable-text gate catches what it cannot read.
     */
    private function extractPdfTextInPhp(string $path): string
    {
        $content = @file_get_contents($path);

        if (! $content || strlen($content) < 100) {
            return '';
        }

        $text = '';

        if (preg_match_all('/\d+\s+\d+\s+obj(.*?)endobj/s', $content, $objects)) {
            foreach ($objects[1] as $object) {
                if (preg_match('#/Subtype\s*/Image#', $object)) {
                    continue;
                }

                if (! preg_match('/stream[\r\n]+(.*?)[\r\n]*endstream/s', $object, $stream)) {
                    continue;
                }

                $data = $stream[1];

                if (preg_match('#/Filter\s*/FlateDecode#', $object)) {
                    $data = $this->inflate($data) ?? '';
                }

                if ($data === '') {
                    continue;
                }

                $extracted = $this->extractTextOperators($data);

                if (trim($extracted) !== '') {
                    $text .= $extracted."\n";
                }
            }
        }

        return trim($text) !== '' ? trim($text) : $this->extractTextOperators($content);
    }

    private function inflate(string $data): ?string
    {
        foreach ([fn () => @gzuncompress($data), fn () => @gzinflate($data)] as $attempt) {
            $result = $attempt();

            if ($result !== false && trim($result) !== '') {
                return $result;
            }
        }

        // Some writers omit the zlib header; re-add the common variants.
        foreach (["\x78\x9c", "\x78\x01", "\x78\xda", "\x78\x5e"] as $prefix) {
            $result = @gzuncompress($prefix.$data);

            if ($result !== false && trim($result) !== '') {
                return $result;
            }
        }

        return null;
    }

    /** Pull strings out of BT/ET text blocks: Tj, TJ, ' and hex literals. */
    private function extractTextOperators(string $data): string
    {
        if (! preg_match_all('/BT(.*?)ET/s', $data, $blocks)) {
            return '';
        }

        $text = '';

        foreach ($blocks[1] as $block) {
            // [(Hello) -50 (World)] TJ
            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $arrays)) {
                foreach ($arrays[1] as $array) {
                    if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/', $array, $parts)) {
                        foreach ($parts[1] as $part) {
                            $text .= $this->decodePdfString($part);
                        }
                    }

                    $text .= ' ';
                }
            }

            // (Hello) Tj  and  (Hello) '
            if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*(?:Tj|\')/', $block, $singles)) {
                foreach ($singles[1] as $single) {
                    $text .= $this->decodePdfString($single).' ';
                }
            }

            // <48656C6C6F> Tj
            if (preg_match_all('/<([0-9A-Fa-f]{4,})>\s*Tj/', $block, $hexes)) {
                foreach ($hexes[1] as $hex) {
                    if (strlen($hex) % 2 !== 0) {
                        continue;
                    }

                    $decoded = @hex2bin($hex);

                    if ($decoded !== false && $this->isReadableText($decoded)) {
                        $text .= $decoded.' ';
                    }
                }
            }
        }

        return trim((string) preg_replace('/[ \t]+/', ' ', $text));
    }

    /** Resolve PDF escape sequences inside a literal string. */
    private function decodePdfString(string $string): string
    {
        $string = preg_replace_callback(
            '/\\\\([0-7]{1,3})/',
            static fn ($m) => chr(octdec($m[1])),
            $string
        ) ?? $string;

        return str_replace(
            ['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'],
            ['(', ')', '\\', "\n", "\r", "\t"],
            $string
        );
    }

    // ───────────────────────── DOCX ─────────────────────────

    private function parseDocx(string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Word files cannot be read on this server. Save the document as a PDF and upload that instead.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('This Word file could not be opened. It may be corrupt — try re-saving it, or paste the text directly.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('This does not look like a Word document. Upload a .docx or PDF file, or paste the text directly.');
        }

        // Convert paragraph and line breaks to real newlines before stripping
        // tags, otherwise the whole document collapses into one line.
        $xml = preg_replace('#</w:p>#', "\n", $xml) ?? $xml;
        $xml = preg_replace('#<w:br\s*/>#', "\n", $xml) ?? $xml;

        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        if (trim($text) === '' || ! $this->isReadableText($text)) {
            throw new RuntimeException('No readable text was found in this Word document. Paste the text directly instead.');
        }

        return $text;
    }

    // ───────────────────────── plain text ─────────────────────────

    private function parsePlainText(string $path): string
    {
        $text = @file_get_contents($path);

        if ($text === false || trim($text) === '') {
            throw new RuntimeException('That file appears to be empty.');
        }

        if (! $this->isReadableText($text)) {
            throw new RuntimeException(
                'That file is not readable as text. If it is a PDF or Word document, upload it with its original extension.'
            );
        }

        return $text;
    }

    // ───────────────────────── helpers ─────────────────────────

    /**
     * Heuristic: is this human-readable text rather than binary?
     *
     * Counts printable ASCII, whitespace and high bytes (UTF-8 continuation
     * bytes, so non-Latin scripts pass) against total length.
     */
    public function isReadableText(string $text): bool
    {
        $length = strlen($text);

        if ($length === 0) {
            return false;
        }

        $printable = 0;

        for ($i = 0; $i < $length; $i++) {
            $ord = ord($text[$i]);

            if (($ord >= 0x20 && $ord <= 0x7E) || $ord >= 0xA0
                || $ord === 0x09 || $ord === 0x0A || $ord === 0x0D) {
                $printable++;
            }
        }

        return ($printable / $length) > self::READABLE_RATIO;
    }

    private function cleanText(string $text): string
    {
        $text = $this->toUtf8($text);

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]{3,}/', '  ', $text) ?? $text;
        $text = preg_replace('/\n{4,}/', "\n\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Force the extracted text into well-formed UTF-8.
     *
     * PDF and DOCX producers routinely emit Windows-1252 bytes — the soft
     * hyphen 0xAD and curly quotes 0x91-0x94 are the usual offenders. Those
     * are legal single bytes in Latin-1 but invalid on their own in UTF-8, and
     * MySQL rejects the whole INSERT with "Incorrect string value" rather than
     * storing them. Converting here means a bad byte can never reach the
     * database, whatever produced the file.
     */
    private function toUtf8(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            // Windows-1252 is a superset of Latin-1 and by far the most common
            // source; it maps every byte, so this cannot fail.
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        // Belt and braces: drop anything still not valid UTF-8.
        $text = @iconv('UTF-8', 'UTF-8//IGNORE', $text) ?: $text;

        // Normalise the punctuation those code pages introduce, so the text
        // reads cleanly and prompts stay compact.
        return strtr($text, [
            "\u{00AD}" => '',      // soft hyphen — invisible, breaks word matching
            "\u{2018}" => "'", "\u{2019}" => "'",
            "\u{201C}" => '"', "\u{201D}" => '"',
            "\u{2013}" => '-', "\u{2014}" => '-',
            "\u{00A0}" => ' ',     // non-breaking space
            "\u{FEFF}" => '',      // byte-order mark
        ]);
    }
}
