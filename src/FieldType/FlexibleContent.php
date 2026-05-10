<?php

declare(strict_types=1);

namespace Stagehand\FieldType;

/**
 * Flexible-content field type — N rows, each row picks one of K layouts.
 *
 * Each row has its own `_layout` key plus the sub-fields defined for that
 * layout. Rows of different layouts can be intermixed and reordered.
 */
final class FlexibleContent
{
    public const TYPE = 'flexible_content';

    /**
     * @param array<int|string, mixed> $posted
     * @param array<int, array<string, mixed>> $layouts
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(array $posted, array $layouts): array
    {
        $by_name = [];
        foreach ($layouts as $layout) {
            $name = (string) ($layout['name'] ?? '');
            if ($name !== '') {
                $by_name[$name] = $layout;
            }
        }

        $rows = [];
        foreach ($posted as $raw_row) {
            if (!is_array($raw_row)) {
                continue;
            }
            $layout_name = (string) ($raw_row['_layout'] ?? '');
            $layout = $by_name[$layout_name] ?? null;
            if ($layout === null) {
                continue;
            }
            $row = ['_layout' => $layout_name];
            foreach (($layout['sub_fields'] ?? []) as $sub) {
                $key = (string) ($sub['name'] ?? '');
                if ($key === '') {
                    continue;
                }
                $row[$key] = isset($raw_row[$key]) ? (string) $raw_row[$key] : '';
            }
            $rows[] = $row;
        }
        return $rows;
    }
}
