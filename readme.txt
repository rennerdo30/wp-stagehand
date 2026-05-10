=== Stagehand ===
Contributors: rennerdo30
Tags: repeater, custom fields, fields, flexible content, acf alternative
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Repeater, flexible-content, and clone field types for WordPress — with a pipe-shorthand textarea as a paste-friendly fallback. MIT, no ACF required.

== Description ==

Stagehand is a free, MIT-licensed alternative to ACF Pro repeaters. It ships both a visual repeater UI AND a pipe-shorthand textarea — editors flip between them with one click.

The textarea isn't a fallback, it's the headline feature. Paste a Notion table, paste a CSV, or just type `13:30 | Doors Open | Bring ID` straight on a phone. Stagehand parses it line-by-line into proper rows.

Field types:

* **Repeater** — N rows of M sub-fields
* **Flexible Content** — N rows, each picking one of K layouts
* **Clone** — reuse a field definition by name

Why not ACF Pro? It costs $59/year. Stagehand is MIT and standalone — no ACF dependency, no SCF migration path needed.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/stagehand/`, or install through the WordPress plugins screen.
2. Activate the plugin.
3. Register fields from your theme's `functions.php`:

`add_action('stagehand_register_fields', function () {
    stagehand_register_field('event_schedule', [
        'post_types' => ['event'],
        'sub_fields' => [
            ['name' => 'time'],
            ['name' => 'label'],
            ['name' => 'note', 'type' => 'textarea'],
        ],
    ]);
});`

== Frequently Asked Questions ==

= Does Stagehand require ACF? =

No. Stagehand is fully standalone.

= Will my data work after I uninstall? =

Stagehand stores data as standard WordPress postmeta under `_stagehand_<field_name>`. After uninstall the data persists; you can read it back with any tool that reads postmeta.

= Is the pipe-shorthand format documented? =

Yes — see the formal grammar in the GitHub README.

== Changelog ==

= 0.1.0 =
* Initial release.
* Repeater, flexible-content, and clone field types.
* Pipe-shorthand parser with full unit-test coverage.
* Classic-editor metabox UI with drag reorder + paste detection.
