<?php

declare(strict_types=1);

namespace Stagehand\Storage;

/**
 * Postmeta serialization layer.
 *
 * Storage shape, per field:
 *   meta_key  = `_stagehand_<field_name>`
 *   meta_val  = [
 *       'v'    => 1,                       // storage version
 *       'rows' => [ row, row, row, ... ],  // each row is associative
 *   ]
 *
 * We wrap the rows in an envelope so future versions can migrate without
 * mistaking legacy raw arrays for the new shape.
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

    public function delete(int $post_id, string $field_name): void
    {
        delete_post_meta($post_id, $this->meta_key($field_name));
    }
}
