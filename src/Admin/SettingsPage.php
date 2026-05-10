<?php

declare(strict_types=1);

namespace Stagehand\Admin;

use Stagehand\FieldRegistry;
use Stagehand\FieldType\Types;
use Stagehand\Plugin;

/**
 * Stagehand → top-level admin menu.
 *
 * Stagehand has no persistent settings — every field is registered at
 * boot via stagehand_register_field() / the stagehand_register_fields
 * action. This page exposes the live registry so site operators can
 * audit which fields exist and which post types they apply to without
 * reading the host theme's source.
 */
final class SettingsPage
{
    private const SLUG = 'stagehand';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
    }

    public function menu(): void
    {
        add_menu_page(
            __('Stagehand', 'stagehand'),
            __('Stagehand', 'stagehand'),
            'manage_options',
            self::SLUG,
            [$this, 'render'],
            'dashicons-editor-table',
            83
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $registry = Plugin::instance()->registry;
        $fields   = $registry->all();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Stagehand', 'stagehand') . '</h1>';
        echo '<p class="description">' . esc_html__('Field types for WordPress — repeater, flexible-content, clone, with pipe-shorthand textarea fallback. ACF-free.', 'stagehand') . '</p>';

        $this->render_overview($fields);

        echo '<hr><h2 class="title">' . esc_html__('Registered fields', 'stagehand') . '</h2>';
        if ($fields === []) {
            $this->render_empty_state();
        } else {
            $this->render_fields_by_post_type($registry, $fields);
        }

        echo '</div>';
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    private function render_overview(array $fields): void
    {
        $total = count($fields);
        $containers = 0;
        $scalars = 0;
        $type_counts = [];
        $post_types = [];
        foreach ($fields as $def) {
            $type = (string) ($def['type'] ?? 'text');
            $type_counts[$type] = ($type_counts[$type] ?? 0) + 1;
            if (Types::isContainer($type)) {
                $containers++;
            } else {
                $scalars++;
            }
            $assigned = $def['post_types'] ?? [];
            if (is_array($assigned)) {
                foreach ($assigned as $pt) {
                    $post_types[(string) $pt] = true;
                }
            }
        }
        ksort($type_counts);

        echo '<h2 class="title">' . esc_html__('Overview', 'stagehand') . '</h2>';
        echo '<table class="widefat striped" style="max-width:780px;"><tbody>';
        echo '<tr><th scope="row" style="width:220px;">' . esc_html__('Plugin version', 'stagehand') . '</th><td><code>' . esc_html(STAGEHAND_VERSION) . '</code></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Registered fields', 'stagehand') . '</th><td>' . esc_html((string) $total) . '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Containers / scalars', 'stagehand') . '</th><td>' . esc_html((string) $containers) . ' / ' . esc_html((string) $scalars) . '</td></tr>';
        if ($type_counts !== []) {
            echo '<tr><th scope="row">' . esc_html__('Field types in use', 'stagehand') . '</th><td>';
            foreach ($type_counts as $type => $count) {
                /* translators: 1: field type name, 2: count */
                $label = sprintf(__('%1$s × %2$d', 'stagehand'), $type, $count);
                echo '<code style="margin-right:8px;">' . esc_html($label) . '</code>';
            }
            echo '</td></tr>';
        }
        if ($post_types !== []) {
            echo '<tr><th scope="row">' . esc_html__('Post types touched', 'stagehand') . '</th><td>';
            foreach (array_keys($post_types) as $pt) {
                echo '<code style="margin-right:8px;">' . esc_html((string) $pt) . '</code>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function render_empty_state(): void
    {
        echo '<div class="notice notice-info inline" style="padding:14px; max-width:780px;">';
        echo '<p><strong>' . esc_html__('No fields registered yet.', 'stagehand') . '</strong> ';
        echo esc_html__('Register fields from your theme (functions.php) or a companion plugin using stagehand_register_field(). Example:', 'stagehand') . '</p>';
        echo '<pre style="background:#0b0b0b;color:#dfe3e6;padding:14px;border-radius:6px;overflow:auto;">';
        echo esc_html(<<<'PHP'
add_action('stagehand_register_fields', function ($registry) {
    $registry->register('event_schedule', [
        'type'       => 'repeater',
        'label'      => 'Run of show',
        'post_types' => ['event'],
        'sub_fields' => [
            ['name' => 'time',  'type' => 'time', 'label' => 'Time'],
            ['name' => 'label', 'type' => 'text', 'label' => 'Cue'],
            ['name' => 'note',  'type' => 'text', 'label' => 'Note'],
        ],
    ]);
});
PHP);
        echo '</pre>';
        echo '</div>';
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     */
    private function render_fields_by_post_type(FieldRegistry $registry, array $fields): void
    {
        // Bucket fields by post-type assignment. Empty post_types means
        // "all post types" — we keep that as its own bucket so it's
        // visually distinct.
        $buckets = [
            '*' => [],
        ];
        foreach ($fields as $name => $def) {
            $assigned = $def['post_types'] ?? [];
            if (!is_array($assigned) || $assigned === []) {
                $buckets['*'][$name] = $def;
                continue;
            }
            foreach ($assigned as $pt) {
                $key = (string) $pt;
                if (!isset($buckets[$key])) {
                    $buckets[$key] = [];
                }
                $buckets[$key][$name] = $def;
            }
        }

        // Render the "all post types" bucket first if non-empty, then
        // alphabetised post-type buckets.
        $ordered = [];
        if ($buckets['*'] !== []) {
            $ordered['*'] = $buckets['*'];
        }
        unset($buckets['*']);
        ksort($buckets);
        foreach ($buckets as $k => $v) {
            $ordered[$k] = $v;
        }

        foreach ($ordered as $pt => $entries) {
            $heading = $pt === '*'
                ? __('All post types', 'stagehand')
                : sprintf(/* translators: %s: post type slug */ __('Post type: %s', 'stagehand'), $pt);
            echo '<h3 style="margin-top:24px;">' . esc_html($heading) . '</h3>';
            echo '<table class="widefat striped" style="max-width:920px;"><thead><tr>';
            echo '<th style="width:240px;">' . esc_html__('Name', 'stagehand') . '</th>';
            echo '<th style="width:140px;">' . esc_html__('Type', 'stagehand') . '</th>';
            echo '<th>' . esc_html__('Label', 'stagehand') . '</th>';
            echo '<th style="width:140px;">' . esc_html__('Sub-fields', 'stagehand') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($entries as $name => $def) {
                $type = (string) ($def['type'] ?? 'text');
                $label = (string) ($def['label'] ?? $name);
                $sub_count = '';
                if (Types::isContainer($type)) {
                    $resolved = $registry->get($name) ?? $def;
                    $subs = $resolved['sub_fields'] ?? [];
                    $sub_count = is_array($subs) ? (string) count($subs) : '0';
                }
                echo '<tr>';
                echo '<td><code>' . esc_html($name) . '</code></td>';
                echo '<td><code>' . esc_html($type) . '</code></td>';
                echo '<td>' . esc_html($label) . '</td>';
                echo '<td>' . esc_html($sub_count) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }
}
