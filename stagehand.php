<?php
/**
 * Plugin Name: Stagehand
 * Plugin URI: https://github.com/rennerdo30/wp-stagehand
 * Description: Repeater, flexible-content, and clone field types for WordPress — with a pipe-shorthand textarea as a paste-friendly fallback. MIT, ACF-free.
 * Version: 0.1.0
 * Author: Renner
 * Author URI: https://renner.dev
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: stagehand
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('STAGEHAND_VERSION', '0.1.0');
define('STAGEHAND_FILE', __FILE__);
define('STAGEHAND_DIR', plugin_dir_path(__FILE__));
define('STAGEHAND_URL', plugin_dir_url(__FILE__));

// Tiny PSR-4 autoloader for the Stagehand\ namespace.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Stagehand\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = STAGEHAND_DIR . 'src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once STAGEHAND_DIR . 'src/Api/Helpers.php';

add_action('plugins_loaded', static function (): void {
    \Stagehand\Plugin::instance()->boot();
});
