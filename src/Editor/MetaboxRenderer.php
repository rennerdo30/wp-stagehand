<?php

declare(strict_types=1);

namespace Stagehand\Editor;

use Stagehand\FieldRegistry;
use Stagehand\FieldType\FlexibleContent;
use Stagehand\FieldType\Repeater;
use Stagehand\FieldType\ScalarField;
use Stagehand\FieldType\Types;
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
        $name = (string) ($field['name'] ?? '');
        $type = (string) ($field['type'] ?? 'repeater');

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        if (Types::isScalar($type)) {
            $this->render_scalar($post_id, $field);
            return;
        }

        $sub_fields = (array) ($field['sub_fields'] ?? []);
        $display    = (string) ($field['display_mode'] ?? 'both');
        $rows       = $this->writer->read($post_id, $name);

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
     * Render a single scalar input (text, image, url, date, color, …).
     *
     * @param array<string, mixed> $field
     */
    private function render_scalar(int $post_id, array $field): void
    {
        $name        = (string) ($field['name'] ?? '');
        $type        = (string) ($field['type'] ?? 'text');
        $value       = $this->writer->read_value($post_id, $name);
        $input_name  = 'stagehand_scalar[' . $name . ']';
        $field_id    = 'stagehand-field-' . sanitize_html_class($name);
        $placeholder = (string) ($field['placeholder'] ?? '');

        echo '<div class="stagehand-field stagehand-scalar" data-stagehand-field="' . esc_attr($name)
            . '" data-stagehand-type="' . esc_attr($type) . '" id="' . esc_attr($field_id) . '">';

        switch ($type) {
            case 'textarea':
                echo '<textarea name="' . esc_attr($input_name) . '" rows="6" class="large-text" placeholder="'
                    . esc_attr($placeholder) . '">' . esc_textarea((string) $value) . '</textarea>';
                break;

            case 'wysiwyg':
                wp_editor((string) $value, sanitize_html_class('stagehand_wysiwyg_' . $name), [
                    'textarea_name' => $input_name,
                    'media_buttons' => true,
                    'tinymce'       => ['height' => 240],
                ]);
                break;

            case 'email':
                echo '<input type="email" name="' . esc_attr($input_name) . '" class="regular-text" value="'
                    . esc_attr((string) $value) . '" placeholder="' . esc_attr($placeholder) . '" />';
                break;

            case 'url':
                echo '<input type="url" name="' . esc_attr($input_name) . '" class="regular-text" value="'
                    . esc_attr((string) $value) . '" placeholder="' . esc_attr($placeholder ?: 'https://') . '" />';
                break;

            case 'date':
                echo '<input type="date" name="' . esc_attr($input_name) . '" value="'
                    . esc_attr((string) $value) . '" />';
                break;

            case 'time':
                echo '<input type="time" name="' . esc_attr($input_name) . '" value="'
                    . esc_attr((string) $value) . '" />';
                break;

            case 'color':
                echo '<input type="color" name="' . esc_attr($input_name) . '" value="'
                    . esc_attr((string) ($value ?: '#000000')) . '" class="stagehand-color" />';
                echo ' <code class="stagehand-color-readout">' . esc_html((string) $value) . '</code>';
                break;

            case 'select':
                $options = (array) ($field['options'] ?? []);
                echo '<select name="' . esc_attr($input_name) . '">';
                if ($placeholder !== '') {
                    echo '<option value="">' . esc_html($placeholder) . '</option>';
                }
                foreach ($options as $key => $label) {
                    $selected = ((string) $key === (string) $value) ? ' selected' : '';
                    echo '<option value="' . esc_attr((string) $key) . '"' . $selected . '>'
                        . esc_html((string) $label) . '</option>';
                }
                echo '</select>';
                break;

            case 'image':
                $att_id  = (int) $value;
                $img_url = $att_id ? (string) wp_get_attachment_image_url($att_id, 'medium') : '';
                echo '<div class="stagehand-image" data-stagehand-image>';
                echo '<input type="hidden" name="' . esc_attr($input_name) . '" value="' . esc_attr((string) $att_id)
                    . '" data-stagehand-image-id />';
                echo '<div class="stagehand-image-preview" data-stagehand-image-preview>';
                if ($img_url !== '') {
                    echo '<img src="' . esc_url($img_url) . '" alt="" />';
                }
                echo '</div>';
                echo '<button type="button" class="button stagehand-image-pick" data-stagehand-image-pick>'
                    . esc_html__('Choose image', 'stagehand') . '</button> ';
                echo '<button type="button" class="button-link stagehand-image-clear" data-stagehand-image-clear>'
                    . esc_html__('Remove', 'stagehand') . '</button>';
                echo '</div>';
                break;

            case 'post_object':
                $multiple  = (bool) ($field['multiple'] ?? false);
                $post_type = $field['post_type'] ?? null;
                $selected  = $multiple
                    ? array_map('intval', is_array($value) ? $value : (is_string($value) && $value !== '' ? explode(',', $value) : []))
                    : [(int) $value];

                $args = [
                    'posts_per_page' => 200,
                    'post_status'    => 'publish',
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ];
                if ($post_type !== null) {
                    $args['post_type'] = $post_type;
                }
                $candidates = get_posts($args);

                $select_name = $multiple ? $input_name . '[]' : $input_name;
                $total       = count($candidates);

                echo '<div class="stagehand-postobject-wrap" data-stagehand-postobject>';
                echo '<input type="text" class="stagehand-postobject-search" data-stagehand-postobject-search'
                    . ' placeholder="' . esc_attr__('Search…', 'stagehand') . '"'
                    . ' aria-label="' . esc_attr__('Filter options', 'stagehand') . '" />';
                echo '<select name="' . esc_attr($select_name) . '"' . ($multiple ? ' multiple size="8" class="stagehand-multiselect"' : '') . '>';
                if (!$multiple) {
                    echo '<option value="">' . esc_html__('— none —', 'stagehand') . '</option>';
                }
                foreach ($candidates as $candidate) {
                    $is_sel = in_array($candidate->ID, $selected, true) ? ' selected' : '';
                    echo '<option value="' . esc_attr((string) $candidate->ID) . '"' . $is_sel . '>'
                        . esc_html(get_the_title($candidate)) . '</option>';
                }
                echo '</select>';
                echo '<p class="stagehand-postobject-count" data-stagehand-postobject-count>'
                    . esc_html(sprintf(
                        /* translators: %d: number of options visible after filtering. */
                        _n('%d result', '%d results', $total, 'stagehand'),
                        $total
                    )) . '</p>';
                echo '</div>';
                break;

            case 'group':
                $sub_fields = (array) ($field['sub_fields'] ?? []);
                $row        = is_array($value) ? $value : [];
                echo '<div class="stagehand-group">';
                foreach ($sub_fields as $sub) {
                    $sub_name  = (string) ($sub['name'] ?? '');
                    if ($sub_name === '') continue;
                    $sub_label = (string) ($sub['label'] ?? $sub_name);
                    $sub_type  = (string) ($sub['type'] ?? 'text');
                    $sub_input = 'stagehand_scalar[' . $name . '][' . $sub_name . ']';
                    $sub_value = (string) ($row[$sub_name] ?? '');
                    echo '<label class="stagehand-sub">';
                    echo '<span class="stagehand-sub-label">' . esc_html($sub_label) . '</span>';
                    if ($sub_type === 'textarea') {
                        echo '<textarea name="' . esc_attr($sub_input) . '" rows="2">' . esc_textarea($sub_value) . '</textarea>';
                    } else {
                        echo '<input type="text" name="' . esc_attr($sub_input) . '" value="' . esc_attr($sub_value) . '" />';
                    }
                    echo '</label>';
                }
                echo '</div>';
                break;

            case 'text':
            default:
                echo '<input type="text" name="' . esc_attr($input_name) . '" class="regular-text" value="'
                    . esc_attr((string) $value) . '" placeholder="' . esc_attr($placeholder) . '" />';
                break;
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
        $scalar_payload = isset($_POST['stagehand_scalar']) && is_array($_POST['stagehand_scalar'])
            ? wp_unslash($_POST['stagehand_scalar'])
            : [];

        foreach ($this->registry->for_post_type($post->post_type) as $field_name => $field) {
            $type       = (string) ($field['type'] ?? 'repeater');
            $sub_fields = (array)  ($field['sub_fields'] ?? []);

            // Scalar branch — single value per field, separate POST namespace.
            if (Types::isScalar($type)) {
                $raw   = $scalar_payload[$field_name] ?? null;
                $value = ScalarField::normalize($raw, $field);
                $this->writer->save_value($post_id, $field_name, $value);
                continue;
            }

            $rows          = [];
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
