<?php

declare(strict_types=1);

namespace Stagehand;

use Stagehand\Editor\MetaboxRenderer;
use Stagehand\Storage\PostMetaWriter;

/**
 * Stagehand bootstrap singleton.
 *
 * Wires the FieldRegistry to WordPress hooks: metabox registration,
 * asset enqueue, and save_post serialization.
 */
final class Plugin
{
    private static ?self $instance = null;

    public readonly FieldRegistry $registry;
    public readonly PostMetaWriter $writer;
    public readonly MetaboxRenderer $renderer;

    private bool $booted = false;

    private function __construct()
    {
        $this->registry = new FieldRegistry();
        $this->writer   = new PostMetaWriter();
        $this->renderer = new MetaboxRenderer($this->registry, $this->writer);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        // Let host code register fields.
        do_action('stagehand_register_fields', $this->registry);

        if (is_admin()) {
            add_action('add_meta_boxes', [$this->renderer, 'register_metaboxes']);
            add_action('save_post', [$this->renderer, 'handle_save'], 10, 2);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        }
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        wp_enqueue_style(
            'stagehand-admin',
            STAGEHAND_URL . 'assets/css/admin-fields.css',
            [],
            STAGEHAND_VERSION
        );
        wp_enqueue_script(
            'stagehand-admin',
            STAGEHAND_URL . 'assets/js/admin-fields.js',
            [],
            STAGEHAND_VERSION,
            true
        );
        wp_localize_script('stagehand-admin', 'StagehandI18n', [
            'addRow'           => __('Add row', 'stagehand'),
            'removeRow'        => __('Remove', 'stagehand'),
            'switchShorthand'  => __('Switch to shorthand', 'stagehand'),
            'switchVisual'     => __('Switch to visual', 'stagehand'),
            'pasteHint'        => __('Paste pipe-shorthand here, one row per line: A | B | C', 'stagehand'),
            'chooseImage'      => __('Choose image', 'stagehand'),
            'useThisImage'     => __('Use this image', 'stagehand'),
        ]);

        // Image fields rely on the WP media frame; loading wp_media() always
        // is cheaper than per-screen detection (it's already cached on most
        // edit screens via the post-thumbnail metabox).
        wp_enqueue_media();
    }
}
