<?php

namespace Tests\Unit;

use App\Support\JsonRepair;
use PHPUnit\Framework\TestCase;

/**
 * Truncated JSON is the single most common AI failure in this pipeline, and
 * the failure is silent — a repair that produces *differently* broken JSON
 * looks the same as no repair at all. These pin the behaviour.
 */
class JsonRepairTest extends TestCase
{
    public function test_strips_markdown_fences(): void
    {
        $this->assertSame('{"a":1}', JsonRepair::stripFences("```json\n{\"a\":1}\n```"));
        $this->assertSame('{"a":1}', JsonRepair::stripFences("```\n{\"a\":1}\n```"));
        $this->assertSame('{"a":1}', JsonRepair::stripFences('{"a":1}'));
    }

    public function test_strips_control_bytes_but_keeps_whitespace_and_utf8(): void
    {
        $input = "{\"a\":\x00\x07\"vál\tue\"}";
        $cleaned = JsonRepair::stripControlBytes($input);

        $this->assertStringNotContainsString("\x00", $cleaned);
        $this->assertStringNotContainsString("\x07", $cleaned);
        $this->assertStringContainsString("\t", $cleaned);
        $this->assertStringContainsString('vál', $cleaned, 'Multi-byte UTF-8 must survive.');
    }

    public function test_repairs_an_array_truncated_mid_string(): void
    {
        $repaired = JsonRepair::repair('{"questions":[{"text":"What is two plus two"},{"text":"Unfini');

        $this->assertIsArray($repaired);
        $this->assertCount(1, $repaired['questions'], 'The incomplete entry should be dropped.');
        $this->assertSame('What is two plus two', $repaired['questions'][0]['text']);
    }

    public function test_repairs_truncation_after_a_trailing_comma(): void
    {
        $repaired = JsonRepair::repair('{"cards":[{"front":"a","back":"b"},');

        $this->assertIsArray($repaired);
        $this->assertCount(1, $repaired['cards']);
    }

    public function test_repairs_a_dangling_key_with_no_value(): void
    {
        $repaired = JsonRepair::repair('{"title":"Photosynthesis","summary":');

        $this->assertIsArray($repaired);
        $this->assertSame('Photosynthesis', $repaired['title']);
        $this->assertArrayNotHasKey('summary', $repaired);
    }

    public function test_repairs_deeply_nested_truncation(): void
    {
        // The trailing 3 is dropped: a literal cut at EOF may itself be
        // truncated (350 arriving as 3), so it is never trusted.
        $repaired = JsonRepair::repair('{"a":{"b":{"c":[1,2,3');

        $this->assertIsArray($repaired);
        $this->assertSame([1, 2], $repaired['a']['b']['c']);
    }

    public function test_keeps_a_number_that_finished_before_the_cut(): void
    {
        $repaired = JsonRepair::repair('{"marks":10,"question":"tr');

        $this->assertIsArray($repaired);
        $this->assertSame(10, $repaired['marks']);
    }

    public function test_repairs_a_truncated_top_level_array(): void
    {
        $repaired = JsonRepair::repair('[{"front":"a","back":"b"},{"front":"c"');

        $this->assertIsArray($repaired);
        // The partial second card survives structurally; the content layer
        // drops it for having no back.
        $this->assertSame('b', $repaired[0]['back']);
        $this->assertArrayNotHasKey('back', $repaired[1]);
    }

    public function test_colons_and_commas_inside_strings_are_not_structural(): void
    {
        $repaired = JsonRepair::repair('{"time":"12:30","list":"a,b,c","next":"cu');

        $this->assertIsArray($repaired);
        $this->assertSame('12:30', $repaired['time']);
        $this->assertSame('a,b,c', $repaired['list']);
    }

    public function test_drops_a_trailing_object_that_never_got_a_member(): void
    {
        $repaired = JsonRepair::repair('{"items":[{"a":1},{');

        $this->assertIsArray($repaired);
        $this->assertCount(1, $repaired['items']);
    }

    /**
     * The bug in the implementation this replaces: counting brackets across
     * the whole document miscounts every bracket inside a string value.
     */
    public function test_brackets_inside_strings_do_not_confuse_the_counter(): void
    {
        $json = '{"note":"use array[0] and {braces}","next":"trunc';
        $repaired = JsonRepair::repair($json);

        $this->assertIsArray($repaired);
        $this->assertSame('use array[0] and {braces}', $repaired['note']);
    }

    public function test_escaped_quotes_do_not_end_a_string_early(): void
    {
        $json = '{"quote":"she said \"hello\" loudly","tail":"cut';
        $repaired = JsonRepair::repair($json);

        $this->assertIsArray($repaired);
        $this->assertSame('she said "hello" loudly', $repaired['quote']);
    }

    public function test_already_valid_json_survives_a_repair_pass(): void
    {
        $repaired = JsonRepair::repair('{"a":[1,2],"b":"x"}');

        $this->assertSame(['a' => [1, 2], 'b' => 'x'], $repaired);
    }

    public function test_returns_null_for_content_that_is_not_json(): void
    {
        $this->assertNull(JsonRepair::repair('I am sorry, I cannot help with that.'));
        $this->assertNull(JsonRepair::repair(''));
    }
}
