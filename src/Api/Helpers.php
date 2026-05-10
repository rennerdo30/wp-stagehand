<?php

declare(strict_types=1);

/**
 * Public API helpers — kept in the global namespace for ergonomic theme use.
 *
 * Themes call:
 *   stagehand_register_field('event_schedule', [ ... ]);
 *   $rows = stagehand_get_rows($post_id, 'event_schedule');
 */

use Stagehand\Plugin;

if (!function_exists('stagehand_register_field')) {
    /**
     * Register a Stagehand field definition.
     *
     * Safe to call on `init` or earlier — definitions are stored in-memory
     * and consulted lazily by the metabox renderer.
     *
     * @param array<string, mixed> $definition
     */
    function stagehand_register_field(string $name, array $definition): void
    {
        Plugin::instance()->registry->register($name, $definition);
    }
}

if (!function_exists('stagehand_get_rows')) {
    /**
     * Read row data for a field on a given post.
     *
     * @return array<int, array<string, mixed>>
     */
    function stagehand_get_rows(int $post_id, string $field_name): array
    {
        return Plugin::instance()->writer->read($post_id, $field_name);
    }
}

if (!function_exists('stagehand_get_field')) {
    /**
     * Look up a field definition by name.
     *
     * @return array<string, mixed>|null
     */
    function stagehand_get_field(string $name): ?array
    {
        return Plugin::instance()->registry->get($name);
    }
}

if (!function_exists('stagehand_get_value')) {
    /**
     * Read a scalar field value, resolved to the format declared by the
     * field's `return` key.
     *
     * Returns:
     *   text / textarea / wysiwyg / url / email / select / color / date / time
     *     → string
     *   image / post_object (single)
     *     → int (attachment / post ID), or WP_Post|array per `return`
     *   post_object (multi)
     *     → int[] (post IDs), or WP_Post[] per `return`
     *   group
     *     → array<string,mixed>
     *
     * Falls back to a flat `get_post_meta()` read when the field has not
     * been registered yet — useful during ACF→Stagehand migrations where
     * postmeta exists but the registration hasn't been ported.
     */
    function stagehand_get_value(int $post_id, string $field_name, mixed $default = null): mixed
    {
        $writer = Plugin::instance()->writer;
        $field  = Plugin::instance()->registry->get($field_name);
        $raw    = $writer->read_value($post_id, $field_name, $default);

        if ($field === null) {
            return $raw;
        }

        $type   = (string) ($field['type'] ?? 'text');
        $return = (string) ($field['return'] ?? 'value');

        switch ($type) {
            case 'image':
                $att_id = (int) $raw;
                if ($att_id <= 0) {
                    return $return === 'array' ? [] : ($return === 'url' ? '' : 0);
                }
                if ($return === 'url') {
                    return (string) wp_get_attachment_url($att_id);
                }
                if ($return === 'array') {
                    $size = (string) ($field['preview_size'] ?? 'large');
                    $src  = wp_get_attachment_image_src($att_id, $size);
                    if (!$src) return [];
                    return [
                        'id'     => $att_id,
                        'url'    => $src[0],
                        'width'  => $src[1],
                        'height' => $src[2],
                        'alt'    => (string) get_post_meta($att_id, '_wp_attachment_image_alt', true),
                    ];
                }
                return $att_id;

            case 'post_object':
                $multiple = (bool) ($field['multiple'] ?? false);
                if ($multiple) {
                    $ids = is_array($raw)
                        ? array_map('intval', $raw)
                        : (is_string($raw) && $raw !== '' ? array_map('intval', explode(',', $raw)) : []);
                    if ($return === 'post') {
                        return array_values(array_filter(array_map('get_post', $ids)));
                    }
                    return array_values($ids);
                }
                $id = (int) $raw;
                if ($id <= 0) {
                    return $return === 'post' ? null : 0;
                }
                return $return === 'post' ? get_post($id) : $id;

            case 'group':
                return is_array($raw) ? $raw : [];

            default:
                return is_string($raw) ? $raw : (string) ($raw ?? '');
        }
    }
}

if (!function_exists('stagehand_parse_shorthand')) {
    /**
     * Parse pipe-shorthand text directly — useful for migrations or CLI imports.
     *
     * @param array<int, array<string, mixed>> $sub_fields
     * @return array<int, array<string, string>>
     */
    function stagehand_parse_shorthand(string $text, array $sub_fields): array
    {
        return \Stagehand\Editor\PipeShorthandParser::parse($text, $sub_fields);
    }
}
