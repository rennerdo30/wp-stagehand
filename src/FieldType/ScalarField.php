<?php

declare(strict_types=1);

namespace Stagehand\FieldType;

/**
 * Scalar field normalization.
 *
 * One static dispatcher for every leaf-input type. Each branch reads the
 * raw POST payload, validates/sanitizes per type, and returns the value
 * in the canonical storage shape:
 *
 *   text / textarea / wysiwyg / url / email / select / color   → string
 *   date / time                                                → string (ISO)
 *   image                                                      → int (attachment ID, 0 when unset)
 *   post_object (single)                                       → int
 *   post_object (multi)                                        → int[]
 *   group                                                      → array<string,mixed> (one row)
 *
 * Container types (repeater / flexible_content / clone) are handled
 * elsewhere — see Repeater::normalize and FlexibleContent::normalize.
 */
final class ScalarField
{
    /**
     * @param array<string,mixed> $field full definition from FieldRegistry::get()
     */
    public static function normalize(mixed $raw, array $field): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        return match ($type) {
            'text', 'select'   => self::scalarString($raw),
            'textarea'         => self::multilineString($raw),
            'wysiwyg'          => self::richText($raw),
            'email'            => self::emailString($raw),
            'url'              => self::urlString($raw),
            'color'            => self::colorString($raw),
            'date'             => self::dateString($raw),
            'time'             => self::timeString($raw),
            'image'            => self::attachmentId($raw),
            'post_object'      => self::postObject($raw, (bool) ($field['multiple'] ?? false)),
            'group'            => self::groupRow($raw, (array) ($field['sub_fields'] ?? [])),
            default            => self::scalarString($raw),
        };
    }

    private static function scalarString(mixed $raw): string
    {
        if (is_array($raw)) return '';
        return sanitize_text_field((string) $raw);
    }

    private static function multilineString(mixed $raw): string
    {
        if (is_array($raw)) return '';
        return sanitize_textarea_field((string) $raw);
    }

    private static function richText(mixed $raw): string
    {
        if (is_array($raw)) return '';
        // wp_kses_post is the canonical post-content sanitizer — preserves
        // editor HTML (images, tables, blocks) while stripping <script>.
        return wp_kses_post((string) $raw);
    }

    private static function emailString(mixed $raw): string
    {
        if (is_array($raw)) return '';
        $value = sanitize_email((string) $raw);
        return $value !== '' && is_email($value) ? $value : '';
    }

    private static function urlString(mixed $raw): string
    {
        if (is_array($raw)) return '';
        $value = trim((string) $raw);
        if ($value === '') return '';
        $clean = esc_url_raw($value);
        return $clean !== '' ? $clean : '';
    }

    private static function colorString(mixed $raw): string
    {
        if (is_array($raw)) return '';
        $value = trim((string) $raw);
        // Accept #abc, #aabbcc, #aabbccdd, or empty.
        if ($value === '') return '';
        if (preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
            return strtolower($value);
        }
        return '';
    }

    private static function dateString(mixed $raw): string
    {
        if (is_array($raw)) return '';
        $value = trim((string) $raw);
        if ($value === '') return '';
        // HTML5 date input: YYYY-MM-DD
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private static function timeString(mixed $raw): string
    {
        if (is_array($raw)) return '';
        $value = trim((string) $raw);
        if ($value === '') return '';
        // HTML5 time input: HH:MM (or HH:MM:SS)
        return preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $value) ? $value : '';
    }

    private static function attachmentId(mixed $raw): int
    {
        if (is_array($raw)) return 0;
        $id = absint((string) $raw);
        if ($id <= 0) return 0;
        // Verify it's actually an attachment so we don't store stale IDs.
        if (get_post_type($id) !== 'attachment') return 0;
        return $id;
    }

    /**
     * @return int|int[]
     */
    private static function postObject(mixed $raw, bool $multiple): int|array
    {
        if ($multiple) {
            $ids = is_array($raw) ? $raw : (is_string($raw) && $raw !== '' ? explode(',', $raw) : []);
            $clean = [];
            foreach ($ids as $id) {
                $i = absint((string) $id);
                if ($i > 0 && get_post_status($i) !== false) {
                    $clean[] = $i;
                }
            }
            return array_values(array_unique($clean));
        }
        $id = absint((string) (is_array($raw) ? ($raw[0] ?? 0) : $raw));
        return ($id > 0 && get_post_status($id) !== false) ? $id : 0;
    }

    /**
     * @param array<int, array<string,mixed>> $sub_fields
     * @return array<string,mixed>
     */
    private static function groupRow(mixed $raw, array $sub_fields): array
    {
        if (!is_array($raw)) return [];
        $row = [];
        foreach ($sub_fields as $sub) {
            $key = (string) ($sub['name'] ?? '');
            if ($key === '') continue;
            $sub_def = $sub;
            $sub_def['type'] = (string) ($sub['type'] ?? 'text');
            // Recurse — nested groups would loop forever, so disallow.
            if ($sub_def['type'] === 'group') $sub_def['type'] = 'text';
            $row[$key] = self::normalize($raw[$key] ?? '', $sub_def);
        }
        return $row;
    }
}
