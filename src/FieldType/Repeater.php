<?php

declare(strict_types=1);

namespace Stagehand\FieldType;

/**
 * Repeater field type — N rows, each with the same set of M sub-fields.
 *
 * This class is a thin descriptor: the actual UI lives in
 * Stagehand\Editor\MetaboxRenderer and the persistence in
 * Stagehand\Storage\PostMetaWriter. We isolate the type-specific
 * normalization and validation here so each type stays cohesive.
 */
final class Repeater
{
    public const TYPE = 'repeater';

    /**
     * Normalize a posted-form payload into an array of row associative arrays.
     *
     * @param array<int|string, mixed> $posted
     * @param array<int, array<string, mixed>> $sub_fields
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(array $posted, array $sub_fields): array
    {
        $rows = [];
        foreach ($posted as $raw_row) {
            if (!is_array($raw_row)) {
                continue;
            }
            $row = [];
            $any = false;
            foreach ($sub_fields as $sub) {
                $key   = (string) ($sub['name'] ?? '');
                if ($key === '') {
                    continue;
                }
                $value = isset($raw_row[$key]) ? (string) $raw_row[$key] : '';
                $row[$key] = $value;
                if ($value !== '') {
                    $any = true;
                }
            }
            if ($any) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $field
     */
    public static function validate(array $rows, array $field): bool
    {
        $min = (int) ($field['min_rows'] ?? 0);
        $max = $field['max_rows'] ?? null;
        $count = count($rows);
        if ($count < $min) {
            return false;
        }
        if ($max !== null && $count > (int) $max) {
            return false;
        }
        return true;
    }
}
