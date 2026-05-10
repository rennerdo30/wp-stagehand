# Stagehand

> Repeater, flexible-content, and clone field types for WordPress — with a
> **pipe-shorthand textarea** as a paste-friendly fallback. MIT, no ACF dependency.

```
stagehand_register_field('event_schedule', [
    'post_types' => ['event'],
    'sub_fields' => [
        ['name' => 'time',  'type' => 'text', 'placeholder' => '13:30'],
        ['name' => 'label', 'type' => 'text'],
        ['name' => 'note',  'type' => 'textarea'],
    ],
]);
```

…and an editor sees both a visual repeater UI **and** this textarea:

```
13:30 | Doors Open       | Bring your ID
14:00 | Cast Greeting
15:00 | Main Performance | Seoul / Taipei / Taichung
18:30 | Encore
19:00 | Curtain Call
```

Either side writes to the same postmeta. Toggle with one click.

---

## Why Stagehand exists

ACF Pro charges **$59/year** for repeaters. SCF (the official ACF fork) ships
the visual UI but not the paste-friendly fallback. While building a real WP
site with ACF Free, the author noticed that a hand-rolled textarea where each
line is `A | B | C` was *better* editor UX than the official repeater for many
use cases — paste-friendly, mobile-friendly, diff-friendly, JS-optional.

**Stagehand productizes that insight.** Editors get the visual UI when they
want it and the textarea when they don't. Devs get the same field-definition
API on both sides.

```
ACF Pro repeater  →  $59/yr, drag UI only,  ACF SDK lock-in
SCF repeater      →  free, drag UI only,    ACF-compatible API
Meta Box repeater →  free core / paid pro,  drag UI only
Stagehand         →  MIT, drag UI + paste textarea, standalone
```

---

## Headline UX wins

- **Paste a Notion table into a textarea** and it parses row-by-row. No more
  clicking *Add Row* five times.
- **Paste shorthand into the FIRST visual cell** and Stagehand auto-explodes
  it into proper rows on the spot.
- **Mobile editing** — typing `13:30 | Doors Open` on a phone is faster than
  three taps, three keyboards, three save toasts.
- **Diff-friendly** — the postmeta serializes the same way ACF does, but the
  shorthand layer means PRs and content-review screenshots are pure text.
- **JS-optional** — every field renders without the admin JS bundle. Good for
  reduced-motion preferences, slow connections, or paranoid editors.

---

## Quick start

```bash
# zip-install or:
git clone https://github.com/rennerdo30/wp-stagehand wp-content/plugins/stagehand
```

Activate, then in `functions.php`:

```php
add_action('stagehand_register_fields', function ($registry) {
    stagehand_register_field('event_schedule', [
        'post_types'   => ['event'],
        'label'        => 'Schedule',
        'sub_fields'   => [
            ['name' => 'time',  'type' => 'text', 'placeholder' => '13:30'],
            ['name' => 'label', 'type' => 'text'],
            ['name' => 'note',  'type' => 'textarea'],
        ],
        'display_mode' => 'both',  // 'visual' | 'shorthand' | 'both'
    ]);
});
```

Render in templates:

```php
$rows = stagehand_get_rows(get_the_ID(), 'event_schedule');
foreach ($rows as $row) {
    printf('<li><time>%s</time> <strong>%s</strong> <em>%s</em></li>',
        esc_html($row['time']),
        esc_html($row['label']),
        esc_html($row['note'])
    );
}
```

---

## Field types

| Type               | What it does                                                    |
|--------------------|-----------------------------------------------------------------|
| `repeater`         | N rows of M sub-fields. The canonical multilingual repeater use case.                      |
| `flexible_content` | N rows, each picks one of K layouts. Each layout has its own sub-fields. |
| `clone`            | Reuse a field def by name (e.g. shared *contact* block).        |

---

## Pipe-shorthand grammar

```
document  := line ( NEWLINE line )*
line      := blank | row
blank     := WS*
row       := cell ( ' | ' cell )*
cell      := ( CHAR | escape )*
escape    := '\\|' | '\\n' | '\\\\'
CHAR      := any character except '\n'
```

Rules:

1. **Delimiter is exactly ` | `** — space-pipe-space. A bare `|` survives.
2. **Backslash escapes:** `\|` → `|`, `\n` → newline (in cell), `\\` → `\`.
3. **Empty lines skipped.** Whitespace-only lines too.
4. **Missing trailing columns** → empty strings.
5. **Extra columns** beyond the field definition are dropped silently.
6. **Whitespace** at cell edges is trimmed.

The parser is pure PHP and unit-tested:

```bash
composer install
composer test
```

---

## Architecture

```
        ┌─────────────────────────────────────────────────┐
        │  functions.php → stagehand_register_field(...)  │
        └────────────────────────┬────────────────────────┘
                                 │
                        ┌────────▼────────┐
                        │  FieldRegistry  │  in-memory store
                        └────────┬────────┘
                                 │
        ┌────────────────────────┼────────────────────────┐
        │                        │                        │
   ┌────▼────┐            ┌──────▼──────┐         ┌───────▼───────┐
   │ Visual  │  toggle    │  Shorthand  │  parse  │ PipeShorthand │
   │ rows UI │ ◄────────► │   textarea  │ ◄─────► │    Parser     │
   └────┬────┘            └──────┬──────┘         └───────────────┘
        │                        │
        └────────────┬───────────┘
                     │ on save_post
              ┌──────▼──────┐
              │ PostMeta    │  → wp_postmeta._stagehand_<field>
              │   Writer    │     { v: 1, rows: [...] }
              └──────┬──────┘
                     │
              ┌──────▼──────────────────┐
              │ stagehand_get_rows(...) │
              └─────────────────────────┘
```

---

## Public API

```php
stagehand_register_field(string $name, array $definition): void
stagehand_get_rows(int $post_id, string $field_name): array
stagehand_get_field(string $name): ?array
stagehand_parse_shorthand(string $text, array $sub_fields): array
```

Hook for registering fields:

```php
do_action('stagehand_register_fields', FieldRegistry $registry);
```

---

## Storage

Every field is stored under `_stagehand_<field_name>` as a versioned envelope:

```php
[
    'v'    => 1,
    'rows' => [
        ['time' => '13:30', 'label' => 'Doors Open',    'note' => 'Bring ID'],
        ['time' => '14:00', 'label' => 'Cast Greeting', 'note' => ''],
    ],
]
```

Future versions will migrate based on `v`.

---

## License

MIT © 2026 Renner — https://renner.dev
