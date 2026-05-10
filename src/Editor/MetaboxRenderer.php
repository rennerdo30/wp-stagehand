<?php

declare(strict_types=1);

namespace Stagehand\Editor;

use Stagehand\FieldRegistry;
use Stagehand\FieldType\FlexibleContent;
use Stagehand\FieldType\Repeater;
use Stagehand\Storage\PostMetaWriter;

/**
 * Classic-editor metabox renderer.
 *
 * For each registered field bound to the current post type, register a
 * metabox showing either the visual repeater UI, the pipe-shorthand
 * textarea, or both (with a toggle). On save, prefer the visual payload
 * if present; fall back to parsing the shorthand textarea.
 */
final class MetaboxRenderer
{
    private const NONCE_ACTION = 'stagehand_save_fields';
    private const NONCE_NAME   = 'stagehand_nonce';

    public function __construct(
        private readonly FieldRegistry $registry,
        private readonly PostMetaWriter $writer,
    ) {
    }

    public function register_metaboxes(string $post_type): void
    {
        foreach ($this->registry->for_post_type($post_type) as $name => $field) {
            add_meta_box(
                'stagehand-' . $name,
                (string) ($field['label'] ?? $name),
                function ($post) use ($field): void {
                    $this->render($post->ID, $field);
                },
                $post_type,
                'normal',
                'default'
            );
        }
    }

    /**
     * @param array<string, mixed> $field
     */
    private function render(int $post_id, array $field): void
    {
        $name        = (string) ($field['name'] ?? '');
        $sub_fields  = (array)  ($field['sub_fields'] ?? []);
        $display     = (string) ($field['display_mode'] ?? 'both');
        $rows        = $this->writer->read($post_id, $name);

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $shorthand   = PipeShorthandParser::emit($rows, $sub_fields);
        $field_id    = 'stagehand-field-' . sanitize_html_class($name);

        echo '<div class="stagehand-field" data-stagehand-field="' . esc_attr($name)
            . '" data-display-mode="' . esc_attr($display) . '" id="' . esc_attr($field_id) . '">';

        if ($display === 'both') {
            echo '<div class="stagehand-toggle">';
            echo '<button type="button" class="button stagehand-mode" data-mode="visual">'
                . esc_html__('Visual', 'stagehand') . '</button>';
            echo '<button type="button" class="button stagehand-mode" data-mode="shorthand">'
                . esc_html__('Shorthand', 'stagehand') . '</button>';
            echo '</div>';
        }

        if (in_array($display, ['visual', 'both'], true)) {
            $this->render_visual($name, $sub_fields, $rows);
        }
        if (in_array($display, ['shorthand', 'both'], true)) {
            $this->render_shorthand($name, $sub_fields, $shorthand);
        }

        echo '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $sub_fields
     * @param array<int, array<string, mixed>> $rows
     */
    private function render_visual(string $field_name, array $sub_fields, array $rows): void
    {
        echo '<div class="stagehand-visual" data-stagehand-visual>';
        echo '<div class="stagehand-rows" data-stagehand-rows>';
        if ($rows === []) {
            $rows = [array_fill_keys(array_map(static fn($s) => (string) ($s['name'] ?? ''), $sub_fields), '')];
        }
        foreach ($rows as $i => $row) {
            $this->render_visual_row($field_name, $sub_fields, $row, (int) $i);
        }
        echo '</div>';
        echo '<button type="button" class="button stagehand-add-row" data-stagehand-add>'
            . esc_html__('Add row', 'stagehand') . '</button>';

        // Hidden template for JS-driven row addition.
        echo '<template data-stagehand-row-template>';
        $this->render_visual_row($field_name, $sub_fields, [], -1);
        echo '</template>';
        echo '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $sub_fields
     * @param array<string, mixed> $row
     */
    private function render_visual_row(string $field_name, array $sub_fields, array $row, int $index): void
    {
        $i = $index < 0 ? '__index__' : (string) $index;
        echo '<div class="stagehand-row" data-stagehand-row>';
        echo '<span class="stagehand-handle" aria-hidden="true">⋮⋮</span>';
        echo '<div class="stagehand-row-fields">';
        foreach ($sub_fields as $sub) {
            $sub_name    = (string) ($sub['name'] ?? '');
            $sub_label   = (string) ($sub['label'] ?? $sub_name);
            $sub_type    = (string) ($sub['type'] ?? 'text');
            $placeholder = (string) ($sub['placeholder'] ?? '');
            $value       = (string) ($row[$sub_name] ?? '');
            $input_name  = 'stagehand[' . $field_name . '][' . $i . '][' . $sub_name . ']';

            echo '<label class="stagehand-sub">';
            echo '<span class="stagehand-sub-label">' . esc_html($sub_label) . '</span>';
            if ($sub_type === 'textarea') {
                echo '<textarea name="' . esc_attr($input_name) . '" rows="2" placeholder="'
                    . esc_attr($placeholder) . '">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input type="text" name="' . esc_attr($input_name) . '" value="'
                    . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '" />';
            }
            echo '</label>';
        }
        echo '</div>';
        echo '<button type="button" class="button-link stagehand-remove" data-stagehand-remove aria-label="'
            . esc_attr__('Remove row', 'stagehand') . '">×</button>';
        echo '</div>';
    }

    /**
     * @param array<int, array<string, mixed>> $sub_fields
     */
    private function render_shorthand(string $field_name, array $sub_fields, string $value): void
    {
        $headers = array_map(
            static fn($s) => (string) ($s['name'] ?? ''),
            $sub_fields
        );
        $hint = sprintf(
            /* translators: %s is a column list e.g. "time | label | note" */
            __('Columns: %s', 'stagehand'),
            implode(' | ', $headers)
        );
        echo '<div class="stagehand-shorthand" data-stagehand-shorthand>';
        echo '<p class="stagehand-shorthand-hint"><code>' . esc_html($hint) . '</code></p>';
        echo '<textarea name="stagehand_shorthand[' . esc_attr($field_name) . ']" rows="8"'
            . ' class="stagehand-shorthand-textarea" placeholder="A | B | C">'
            . esc_textarea($value) . '</textarea>';
        echo '</div>';
    }

    public function handle_save(int $post_id, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST[self::NONCE_NAME])) {
            return;
        }
        $nonce = sanitize_text_field((string) wp_unslash($_POST[self::NONCE_NAME]));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $visual_payload = isset($_POST['stagehand']) && is_array($_POST['stagehand'])
            ? wp_unslash($_POST['stagehand'])
            : [];
        $shorthand_payload = isset($_POST['stagehand_shorthand']) && is_array($_POST['stagehand_shorthand'])
            ? wp_unslash($_POST['stagehand_shorthand'])
            : [];

        foreach ($this->registry->for_post_type($post->post_type) as $field_name => $field) {
            $type       = (string) ($field['type'] ?? 'repeater');
            $sub_fields = (array)  ($field['sub_fields'] ?? []);
            $rows       = [];

            $has_visual    = isset($visual_payload[$field_name]) && is_array($visual_payload[$field_name]);
            $has_shorthand = isset($shorthand_payload[$field_name])
                && is_string($shorthand_payload[$field_name])
                && trim((string) $shorthand_payload[$field_name]) !== '';

            if ($has_visual) {
                $rows = $type === FlexibleContent::TYPE
                    ? FlexibleContent::normalize($visual_payload[$field_name], (array) ($field['layouts'] ?? []))
                    : Repeater::normalize($visual_payload[$field_name], $sub_fields);
            } elseif ($has_shorthand) {
                $rows = PipeShorthandParser::parse((string) $shorthand_payload[$field_name], $sub_fields);
            } else {
                continue;
            }

            $this->writer->save($post_id, $field_name, $rows);
        }
    }
}
