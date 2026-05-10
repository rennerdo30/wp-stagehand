<?php

declare(strict_types=1);

namespace Stagehand\Storage;

/**
 * Postmeta serialization layer.
 *
 * Two envelope shapes share the same `_stagehand_<field_name>` meta key
 * space — the writer picks one based on the field type:
 *
 *   Container (repeater / flexible_content / clone):
 *     [ 'v' => 1, 'rows' => [ row, row, … ] ]
 *
 *   Scalar (text, image, url, post_object, group, …):
 *     [ 'v' => 1, 'value' => <mixed> ]
 *
 * Readers check key presence (`rows` vs `value`) to disambiguate, so
 * envelope shapes can coexist in the same install during migrations.
 */
final class PostMetaWriter
{
    public const STORAGE_VERSION = 1;
    public const META_PREFIX     = '_stagehand_';

    public function meta_key(string $field_name): string
    {
        return self::META_PREFIX . $field_name;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function save(int $post_id, string $field_name, array $rows): void
    {
        $envelope = [
            'v'    => self::STORAGE_VERSION,
            'rows' => array_values($rows),
        ];
        update_post_meta($post_id, $this->meta_key($field_name), $envelope);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function read(int $post_id, string $field_name): array
    {
        $value = get_post_meta($post_id, $this->meta_key($field_name), true);
        if (!is_array($value)) {
            return [];
        }
        // v1 envelope.
        if (isset($value['v'], $value['rows']) && is_array($value['rows'])) {
            return $value['rows'];
        }
        // Legacy raw array (no envelope) — assume it's already a list of rows.
        if (array_is_list($value)) {
            return $value;
        }
        return [];
    }

    /**
     * Persist a scalar value (single field, no row collection).
     *
     * Empty strings and nulls delete the meta key entirely so unset fields
     * round-trip cleanly through migrations.
     */
    public function save_value(int $post_id, string $field_name, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            $this->delete($post_id, $field_name);
            return;
        }
        $envelope = [
            'v'     => self::STORAGE_VERSION,
            'value' => $value,
        ];
        update_post_meta($post_id, $this->meta_key($field_name), $envelope);
    }

    /**
     * Read a scalar value. Returns the raw stored shape — callers (or the
     * stagehand_get_value() helper) interpret it per field type.
     */
    public function read_value(int $post_id, string $field_name, mixed $default = null): mixed
    {
        $stored = get_post_meta($post_id, $this->meta_key($field_name), true);
        if (!is_array($stored)) {
            // Pre-Stagehand or legacy postmeta — surface the raw value as-is
            // so theme migrations off ACF (which writes flat postmeta) can
            // read existing data without a forced rewrite.
            return $stored === '' ? $default : $stored;
        }
        if (array_key_exists('value', $stored)) {
            return $stored['value'];
        }
        return $default;
    }

    public function delete(int $post_id, string $field_name): void
    {
        delete_post_meta($post_id, $this->meta_key($field_name));
    }
}
