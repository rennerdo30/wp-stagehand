<?php

declare(strict_types=1);

namespace Stagehand\FieldType;

/**
 * Clone field type — references another field's sub_fields by name.
 *
 * `Clone` is a reserved keyword in PHP; the file is named Clone.php but the
 * class is exposed as `CloneField` to keep `use` statements legal.
 *
 * Usage:
 *   stagehand_register_field('contact_block', [
 *       'sub_fields' => [
 *           ['name' => 'email', 'type' => 'text'],
 *           ['name' => 'phone', 'type' => 'text'],
 *       ],
 *   ]);
 *   stagehand_register_field('event_contact', [
 *       'type'     => 'clone',
 *       'clone_of' => 'contact_block',
 *   ]);
 *
 * Resolution happens in FieldRegistry::get() — by the time the renderer
 * reads the definition, sub_fields have been hydrated from the source.
 */
final class CloneField
{
    public const TYPE = 'clone';
}
