<?php

declare(strict_types=1);

namespace Stagehand;

/**
 * Global, in-memory field-definition store.
 *
 * Field definitions are registered at boot (typically from the host theme's
 * functions.php) and looked up by name when rendering metaboxes or reading
 * postmeta back into row arrays.
 */
final class FieldRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $fields = [];

    /**
     * @param array<string, mixed> $definition
     */
    public function register(string $name, array $definition): void
    {
        $definition['name']         = $name;
        $definition['type']         = $definition['type']         ?? 'repeater';
        $definition['label']        = $definition['label']        ?? $name;
        $definition['post_types']   = $definition['post_types']   ?? [];
        $definition['sub_fields']   = $definition['sub_fields']   ?? [];
        $definition['layouts']      = $definition['layouts']      ?? [];
        $definition['min_rows']     = $definition['min_rows']     ?? 0;
        $definition['max_rows']     = $definition['max_rows']     ?? null;
        $definition['display_mode'] = $definition['display_mode'] ?? 'both';
        $definition['clone_of']     = $definition['clone_of']     ?? null;

        $this->fields[$name] = $definition;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array
    {
        $definition = $this->fields[$name] ?? null;
        if ($definition === null) {
            return null;
        }
        // Resolve `clone` field types: copy sub_fields from the source.
        if (($definition['type'] ?? '') === 'clone' && !empty($definition['clone_of'])) {
            $source = $this->fields[$definition['clone_of']] ?? null;
            if ($source !== null) {
                $definition['sub_fields'] = $source['sub_fields'] ?? [];
                $definition['layouts']    = $source['layouts'] ?? [];
            }
        }
        return $definition;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function for_post_type(string $post_type): array
    {
        $matches = [];
        foreach ($this->fields as $name => $field) {
            $post_types = $field['post_types'] ?? [];
            if (empty($post_types) || in_array($post_type, (array) $post_types, true)) {
                $matches[$name] = $this->get($name) ?? $field;
            }
        }
        return $matches;
    }
}
