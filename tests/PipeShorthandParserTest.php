<?php

declare(strict_types=1);

namespace Stagehand\Tests;

use PHPUnit\Framework\TestCase;
use Stagehand\Editor\PipeShorthandParser;

final class PipeShorthandParserTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function scheduleSubFields(): array
    {
        return [
            ['name' => 'time'],
            ['name' => 'label'],
            ['name' => 'note'],
        ];
    }

    public function test_parses_simple_three_column_rows(): void
    {
        $text = "13:30 | Doors Open | Bring your ID\n14:00 | Cast Greeting | \n15:00 | Main | Seoul";
        $rows = PipeShorthandParser::parse($text, $this->scheduleSubFields());
        $this->assertCount(3, $rows);
        $this->assertSame('13:30', $rows[0]['time']);
        $this->assertSame('Doors Open', $rows[0]['label']);
        $this->assertSame('Bring your ID', $rows[0]['note']);
        $this->assertSame('Seoul', $rows[2]['note']);
    }

    public function test_missing_trailing_columns_become_empty_strings(): void
    {
        $text = "14:00 | Cast Greeting\n18:30 | Encore";
        $rows = PipeShorthandParser::parse($text, $this->scheduleSubFields());
        $this->assertCount(2, $rows);
        $this->assertSame('', $rows[0]['note']);
        $this->assertSame('Encore', $rows[1]['label']);
        $this->assertSame('', $rows[1]['note']);
    }

    public function test_blank_and_whitespace_lines_are_skipped(): void
    {
        $text = "\n  \n13:30 | A | B\n\n\n14:00 | C | D\n   \n";
        $rows = PipeShorthandParser::parse($text, $this->scheduleSubFields());
        $this->assertCount(2, $rows);
        $this->assertSame('13:30', $rows[0]['time']);
        $this->assertSame('14:00', $rows[1]['time']);
    }

    public function test_escaped_pipe_is_preserved_literally(): void
    {
        $text = '13:30 | Pipes \| inside content | safe';
        $rows = PipeShorthandParser::parse($text, $this->scheduleSubFields());
        $this->assertCount(1, $rows);
        $this->assertSame('Pipes | inside content', $rows[0]['label']);
        $this->assertSame('safe', $rows[0]['note']);
    }

    public function test_literal_backslash_n_expands_to_newline_in_cell(): void
    {
        $sub = [['name' => 'title'], ['name' => 'body']];
        $text = 'Headline | line one\nline two';
        $rows = PipeShorthandParser::parse($text, $sub);
        $this->assertSame("line one\nline two", $rows[0]['body']);
    }

    public function test_extra_columns_beyond_definition_are_ignored(): void
    {
        $sub = [['name' => 'a'], ['name' => 'b']];
        $text = 'one | two | three | four';
        $rows = PipeShorthandParser::parse($text, $sub);
        $this->assertCount(1, $rows);
        $this->assertSame('one', $rows[0]['a']);
        $this->assertSame('two', $rows[0]['b']);
        $this->assertArrayNotHasKey('c', $rows[0]);
    }

    public function test_edge_whitespace_in_cells_is_trimmed(): void
    {
        $text = "   13:30   |   Doors Open    |    Bring ID   ";
        $rows = PipeShorthandParser::parse($text, $this->scheduleSubFields());
        $this->assertSame('13:30', $rows[0]['time']);
        $this->assertSame('Doors Open', $rows[0]['label']);
        $this->assertSame('Bring ID', $rows[0]['note']);
    }

    public function test_bare_pipe_without_surrounding_spaces_is_kept_as_content(): void
    {
        // ` |` without trailing space should not split.
        $text = 'time-a | label|with-bar | note';
        $rows = PipeShorthandParser::parse($text, $this->scheduleSubFields());
        $this->assertSame('time-a', $rows[0]['time']);
        $this->assertSame('label|with-bar', $rows[0]['label']);
        $this->assertSame('note', $rows[0]['note']);
    }

    public function test_emit_round_trips_simple_rows(): void
    {
        $sub = $this->scheduleSubFields();
        $rows = [
            ['time' => '13:30', 'label' => 'Doors Open', 'note' => 'Bring ID'],
            ['time' => '14:00', 'label' => 'Cast Greeting', 'note' => ''],
        ];
        $emitted = PipeShorthandParser::emit($rows, $sub);
        $reparsed = PipeShorthandParser::parse($emitted, $sub);
        $this->assertSame($rows, $reparsed);
    }

    public function test_emit_escapes_pipe_and_newline(): void
    {
        $sub = [['name' => 'a'], ['name' => 'b']];
        $rows = [['a' => 'has | pipe', 'b' => "two\nlines"]];
        $emitted = PipeShorthandParser::emit($rows, $sub);
        $this->assertStringContainsString('\\|', $emitted);
        $this->assertStringContainsString('\\n', $emitted);
        $reparsed = PipeShorthandParser::parse($emitted, $sub);
        $this->assertSame('has | pipe', $reparsed[0]['a']);
        $this->assertSame("two\nlines", $reparsed[0]['b']);
    }

    public function test_crlf_line_endings_are_normalized(): void
    {
        $text = "13:30 | A | B\r\n14:00 | C | D\r\n";
        $rows = PipeShorthandParser::parse($text, $this->scheduleSubFields());
        $this->assertCount(2, $rows);
        $this->assertSame('14:00', $rows[1]['time']);
    }
}
