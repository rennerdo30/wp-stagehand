<?php

declare(strict_types=1);

namespace Stagehand\FieldType;

/**
 * Type taxonomy.
 *
 * Container types hold N sub-field rows (repeater), N typed rows
 * (flexible_content), or borrow another field's sub-fields (clone). They
 * are stored as `{ v:1, rows:[...] }` envelopes and read via
 * stagehand_get_rows().
 *
 * Scalar types are leaf inputs: a single string, integer, or simple
 * associative array (group). They're stored as `{ v:1, value:... }`
 * envelopes and read via stagehand_get_value().
 *
 * The split exists because the storage shape and the editor UI diverge
 * fundamentally — repeaters need add/remove row controls, scalars don't.
 */
final class Types
{
    public const CONTAINER = [
        Repeater::TYPE,
        FlexibleContent::TYPE,
        CloneField::TYPE,
    ];

    public const SCALAR = [
        'text',
        'textarea',
        'wysiwyg',
        'email',
        'url',
        'date',
        'time',
        'color',
        'select',
        'image',
        'post_object',
        'group',
    ];

    public static function isContainer(string $type): bool
    {
        return in_array($type, self::CONTAINER, true);
    }

    public static function isScalar(string $type): bool
    {
        return in_array($type, self::SCALAR, true);
    }
}
