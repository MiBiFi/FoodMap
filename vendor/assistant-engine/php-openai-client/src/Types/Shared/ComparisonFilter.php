<?php

namespace AssistantEngine\OpenAI\Types\Shared;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

/**
 * Class ComparisonFilter
 *
 * Represents a filter that compares a key to a value using an operator.
 */
class ComparisonFilter implements HydratableInterface
{
    /**
     * The key to compare against the value.
     *
     * @var string
     */
    public string $key;

    /**
     * Comparison operator. One of: "eq", "ne", "gt", "gte", "lt", "lte".
     *
     * @var string
     */
    public string $type;

    /**
     * The value to compare against the attribute key.
     * Supports string, number, or boolean.
     *
     * @var mixed
     */
    public $value;

    /**
     * Hydrates a ComparisonFilter instance from an associative array.
     *
     * @param array $data
     * @return ComparisonFilter
     * @throws \Exception if required fields are missing.
     */
    public static function fromArray(array $data): self {
        if (!isset($data['key']) || !is_string($data['key'])) {
            throw new \Exception("ComparisonFilter requires a valid 'key' field.");
        }
        if (!isset($data['type']) || !in_array($data['type'], ['eq', 'ne', 'gt', 'gte', 'lt', 'lte'], true)) {
            throw new \Exception("ComparisonFilter requires a valid 'type' field. Allowed values: eq, ne, gt, gte, lt, lte.");
        }
        if (!array_key_exists('value', $data)) {
            throw new \Exception("ComparisonFilter requires a 'value' field.");
        }

        $instance = new self();
        $instance->key = $data['key'];
        $instance->type = $data['type'];
        $instance->value = $data['value'];
        return $instance;
    }
}