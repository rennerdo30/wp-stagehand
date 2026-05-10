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
