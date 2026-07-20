<?php

namespace App\Services\Library;

/**
 * Validates params.json schemas and data.json sample values against them
 * (SPEC §3.1–3.3). Every param must define a default so every component
 * renders standalone.
 */
class ParamsValidator
{
    /**
     * @var list<string>
     */
    private const TYPES = ['string', 'text', 'number', 'boolean', 'enum', 'image', 'url', 'array', 'object'];

    /**
     * Validate a params.json schema.
     *
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function validateSchema(array $params, string $context = 'params.json'): array
    {
        $errors = [];

        foreach ($params as $name => $definition) {
            $label = "{$context}: param '{$name}'";

            if (! is_array($definition)) {
                $errors[] = "{$label} must be an object with type, default and description";

                continue;
            }

            $type = $definition['type'] ?? null;

            if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
                $errors[] = "{$label} has invalid type '".(is_string($type) ? $type : gettype($type))."'";
            }

            if (! array_key_exists('default', $definition)) {
                $errors[] = "{$label} must define a default";
            }

            if (! isset($definition['description']) || ! is_string($definition['description'])) {
                $errors[] = "{$label} must define a description string";
            }

            if ($type === 'enum') {
                $options = $definition['options'] ?? null;

                if (! is_array($options) || $options === []) {
                    $errors[] = "{$label} (enum) must define a non-empty options array";
                } elseif (array_key_exists('default', $definition) && ! in_array($definition['default'], $options, true)) {
                    $errors[] = "{$label} (enum) default must be one of its options";
                }
            }

            if (is_string($type) && in_array($type, self::TYPES, true) && array_key_exists('default', $definition)) {
                $defaultError = $this->checkType($definition['default'], $type, $definition);

                if ($defaultError !== null) {
                    $errors[] = "{$label} default {$defaultError}";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a data.json object against a params schema. The reserved
     * `children` key (composite child slices) is validated separately.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function validateData(array $data, array $params, string $context = 'data.json'): array
    {
        $errors = [];

        foreach ($data as $name => $value) {
            if ($name === 'children') {
                continue;
            }

            if (! isset($params[$name]) || ! is_array($params[$name])) {
                $errors[] = "{$context}: '{$name}' is not defined in params.json";

                continue;
            }

            $type = $params[$name]['type'] ?? null;

            if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
                continue;
            }

            $typeError = $this->checkType($value, $type, $params[$name]);

            if ($typeError !== null) {
                $errors[] = "{$context}: param '{$name}' {$typeError}";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function checkType(mixed $value, string $type, array $definition): ?string
    {
        $valid = match ($type) {
            'string', 'text', 'image', 'url' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'enum' => in_array($value, $definition['options'] ?? [], true),
            'array' => is_array($value),
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            default => true,
        };

        if ($valid) {
            return null;
        }

        return match ($type) {
            'enum' => 'must be one of its options',
            default => "must be of type {$type}",
        };
    }
}
