<?php

declare(strict_types=1);

namespace Stagehand\Editor;

/**
 * Pipe-shorthand parser — the heart of Stagehand.
 *
 * Format:
 *   - One row per line.
 *   - Columns separated by ` | ` (space-pipe-space). The flanking spaces are
 *     part of the delimiter so a bare `|` inside content survives.
 *   - Empty lines are skipped.
 *   - A backslash escapes a pipe: `\|` becomes a literal `|`.
 *   - A literal `\n` (two characters) inside a column expands to a newline.
 *   - Trailing/leading whitespace per column is trimmed.
 *   - Missing columns at the end of a row become empty strings.
 *   - Extra columns past the sub-field count are ignored.
 *
 * The parser is pure — no WordPress globals, no hooks. That keeps it unit
 * testable without a full WP bootstrap.
 */
final class PipeShorthandParser
{
    /**
     * @param array<int, array<string, mixed>> $sub_fields
     * @return array<int, array<string, string>>
     */
    public static function parse(string $text, array $sub_fields): array
    {
        $names = [];
        foreach ($sub_fields as $sub) {
            $name = (string) ($sub['name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }
        if ($names === []) {
            return [];
        }

        // Normalize line endings then split.
        $text  = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $columns = self::split_columns($line);
            $row = [];
            foreach ($names as $i => $name) {
                $cell = $columns[$i] ?? '';
                $row[$name] = self::clean_cell($cell);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Reverse direction: render row arrays back into pipe-shorthand text.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $sub_fields
     */
    public static function emit(array $rows, array $sub_fields): string
    {
        $names = [];
        foreach ($sub_fields as $sub) {
            $name = (string) ($sub['name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }
        $lines = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($names as $name) {
                $value = (string) ($row[$name] ?? '');
                $value = str_replace(['\\', '|', "\n"], ['\\\\', '\\|', '\\n'], $value);
                $cells[] = $value;
            }
            // Drop empty trailing cells — they round-trip via missing columns.
            while ($cells !== [] && end($cells) === '') {
                array_pop($cells);
            }
            if ($cells === []) {
                continue;
            }
            $lines[] = implode(' | ', $cells);
        }
        return implode("\n", $lines);
    }

    /**
     * Split a single line on ` | `, respecting backslash escapes.
     *
     * @return array<int, string>
     */
    private static function split_columns(string $line): array
    {
        $columns = [];
        $buffer  = '';
        $len     = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            $ch = $line[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                // Preserve the escape — clean_cell() unescapes after splitting.
                $buffer .= $ch . $line[$i + 1];
                $i++;
                continue;
            }
            // Match exact ` | ` boundary: prev char is space, next is space.
            if ($ch === '|'
                && $i > 0 && $line[$i - 1] === ' '
                && $i + 1 < $len && $line[$i + 1] === ' '
            ) {
                // Trim the trailing space we already pushed into $buffer.
                $columns[] = rtrim($buffer);
                $buffer = '';
                $i++; // skip the trailing space after the pipe
                continue;
            }
            $buffer .= $ch;
        }
        $columns[] = $buffer;
        return $columns;
    }

    private static function clean_cell(string $cell): string
    {
        $cell = trim($cell);
        // Two-pass unescape: \| → |, \n → newline, \\ → \
        $out = '';
        $len = strlen($cell);
        for ($i = 0; $i < $len; $i++) {
            $ch = $cell[$i];
            if ($ch === '\\' && $i + 1 < $len) {
                $next = $cell[$i + 1];
                $out .= match ($next) {
                    '|'     => '|',
                    'n'     => "\n",
                    '\\'    => '\\',
                    default => $ch . $next,
                };
                $i++;
                continue;
            }
            $out .= $ch;
        }
        return $out;
    }
}
