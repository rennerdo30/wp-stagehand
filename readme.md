# Stagehand

> Field types for WordPress — repeater, flexible-content, clone (with a
> **pipe-shorthand textarea** fallback), plus a full set of scalar leaf types:
> text, textarea, wysiwyg, email, url, date, time, color, select, image,
> post_object, group. MIT, no ACF dependency.

```php
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
the visual UI but not the paste-friendly fallback. Most "free" repeater
plugins are still drag-only and skip the leaf field types you need to build
real product UI without ACF.

Stagehand productizes two ideas in one plugin:

1. **Visual ↔ pipe-shorthand toggle** for container fields (repeater,
   flexible-content, clone) — editors get the click-to-add-row UI when they
   want it and a `A | B | C`-per-line textarea when they don't.
2. **A full set of scalar leaf types** (v0.2.0) so a theme can declare its
   entire field schema through Stagehand without falling back to ACF or
   hand-rolled metaboxes for the simple cases.

```
ACF Pro repeater  →  $59/yr, drag UI only,  ACF SDK lock-in
SCF repeater      →  free, drag UI only,    ACF-compatible API
Meta Box repeater →  free core / paid pro,  drag UI only
Stagehand         →  MIT, drag UI + paste textarea + scalar leaf types,
                     standalone
```

---

## Headline UX wins

- **Paste a Notion table into a textarea** and it parses row-by-row. No more
  clicking *Add Row* five times.
- **Paste shorthand into the FIRST visual cell** and Stagehand auto-explodes
  it into proper rows on the spot.
- **Mobile editing** — typing `13:30 | Doors Open` on a phone is faster than
  three taps, three keyboards, three save toasts.
- **Diff-friendly** — the postmeta envelope stores rows as a versioned
  array, but the shorthand layer means PRs and content-review screenshots
  are pure text.
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
add_action('stagehand_register_fields', function () {
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

Read scalars the same way:

```php
$intro     = stagehand_get_value(get_the_ID(), 'page_intro');         // string
$logo_id   = stagehand_get_value(get_the_ID(), 'partner_logo');       // int (attachment ID)
$related   = stagehand_get_value(get_the_ID(), 'related_events');     // int[] (post IDs)
```

---

## Field types

### Container types (rows)

| Type               | What it does                                                                                  |
|--------------------|-----------------------------------------------------------------------------------------------|
| `repeater`         | N rows of M sub-fields. Rendered as the visual UI, the pipe-shorthand textarea, or both.       |
| `flexible_content` | N rows, each picks one of K layouts. Each layout has its own sub-fields.                       |
| `clone`            | Reuse a field def by name (e.g. shared *contact* block).                                       |

### Scalar leaf types (v0.2.0)

| Type           | Stored as                          | `return` modes                          |
|----------------|------------------------------------|-----------------------------------------|
| `text`         | string                             | `value` (default)                       |
| `textarea`     | string                             | `value`                                 |
| `wysiwyg`      | string (HTML)                      | `value`                                 |
| `email`        | string                             | `value`                                 |
| `url`          | string                             | `value`                                 |
| `date`         | string (`Y-m-d`)                   | `value`                                 |
| `time`         | string (`H:i`)                     | `value`                                 |
| `color`        | string (`#rrggbb`)                 | `value`                                 |
| `select`       | string                             | `value`                                 |
| `image`        | int (attachment ID)                | `value` (ID), `url`, `array`            |
| `post_object`  | int or int[] (post IDs)            | `value` (ID/IDs), `post` (`WP_Post[]`)  |
| `group`        | array<string, mixed>               | `array`                                 |

`stagehand_get_value()` resolves to the format declared by the field's
`return` key — pass `'return' => 'url'` on an image field to read it as an
upload URL, `'return' => 'post'` on a post_object multi to read `WP_Post[]`,
etc.

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
              │   Writer    │     containers: { v: 1, rows:  [...] }
              │             │     scalars:    { v: 1, value: ... }
              └──────┬──────┘
                     │
              ┌──────▼─────────────────────────┐
              │ stagehand_get_rows  / _value() │
              └────────────────────────────────┘
```

---

## Public API

```php
stagehand_register_field(string $name, array $definition): void
stagehand_get_rows(int $post_id, string $field_name): array
stagehand_get_value(int $post_id, string $field_name, mixed $default = null): mixed
stagehand_get_field(string $name): ?array
stagehand_parse_shorthand(string $text, array $sub_fields): array
```

Hook for registering fields:

```php
do_action('stagehand_register_fields');
```

---

## Storage

Every field is stored under `_stagehand_<field_name>` as a versioned envelope.

**Containers** (`repeater`, `flexible_content`, `clone`):

```php
[
    'v'    => 1,
    'rows' => [
        ['time' => '13:30', 'label' => 'Doors Open',    'note' => 'Bring ID'],
        ['time' => '14:00', 'label' => 'Cast Greeting', 'note' => ''],
    ],
]
```

**Scalars** (everything else):

```php
[
    'v'     => 1,
    'value' => 'Hello world',  // or int, int[], or array<string,mixed> for `group`
]
```

### Legacy postmeta fallback

`stagehand_get_value()` reads through this resolution chain so themes
migrating off ACF surface their existing data without a rewrite:

1. `_stagehand_<field>` v1 envelope (canonical Stagehand storage).
2. `_stagehand_<field>` raw scalar (pre-envelope, future-proof).
3. **`<field>` flat postmeta** — the shape ACF writes by default. Stagehand
   reads it transparently so `stagehand_get_value($id, 'page_intro')` keeps
   working on posts that were saved before the theme was ported off ACF.

Future versions will migrate based on `v`.

---

## Known limitations

- **No conditional logic.** Field visibility is post-type-scoped only;
  there's no "show field X when field Y equals Z". Most sites can model
  conditional UX with `flexible_content` instead.
- **No location rules beyond `post_types`.** Term-edit screens, user
  profiles, and options pages aren't supported yet — Stagehand registers
  metaboxes against `add_meta_box()` only.
- **No revisions integration.** Postmeta written by Stagehand isn't
  attached to WP revisions; rolling back a post does not roll back its
  Stagehand fields.
- **No translation hooks.** Each field stores one value per post. Pair
  with [Triptych](https://github.com/rennerdo30/wp-triptych) or another
  multilingual layer if you need per-language values.

---

## License

MIT © 2026 Renner — https://renner.dev
