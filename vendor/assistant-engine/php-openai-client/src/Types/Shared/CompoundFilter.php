<?php

namespace AssistantEngine\OpenAI\Types\Shared;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

/**
 * Class CompoundFilter
 *
 * Represents a compound filter that combines multiple filters using a logical operator.
 * Each item in the filters array can be either a ComparisonFilter or another CompoundFilter.
 */
class CompoundFilter implements HydratableInterface
{
    /**
     * Array of filters to combine.
     * Each element should be either a ComparisonFilter or a CompoundFilter.
     *
     * @var array
     */
    public array $filters;

    /**
     * Logical operator for the compound filter.
     * Must be either "and" or "or".
     *
     * @var string
     */
    public string $type;

    /**
     * Hydrates a CompoundFilter instance from an associative array.
     *
     * @param array $data
     * @return CompoundFilter
     * @throws \Exception if required fields are missing or invalid.
     */
    public static function fromArray(array $data): self {
        if (!isset($data['filters']) || !is_array($data['filters'])) {
            throw new \Exception("CompoundFilter requires a valid 'filters' array.");
        }
        if (!isset($data['type']) || !in_array($data['type'], ['and', 'or'], true)) {
            throw new \Exception("CompoundFilter requires a valid 'type' field. Allowed values: and, or.");
        }

        $instance = new self();
        $instance->type = $data['type'];
        $instance->filters = [];

        foreach ($data['filters'] as $filterData) {
            if (!isset($filterData['type'])) {
                throw new \Exception("Each filter item must have a 'type' field.");
            }
            $filterType = $filterData['type'];
            if (in_array($filterType, ['and', 'or'], true)) {
                // Nested compound filter
                $instance->filters[] = self::fromArray($filterData);
            } elseif (in_array($filterType, ['eq', 'ne', 'gt', 'gte', 'lt', 'lte'], true)) {
                // Comparison filter
                $instance->filters[] = ComparisonFilter::fromArray($filterData);
            } else {
                throw new \Exception("Unknown filter type: " . $filterType);
            }
        }
        return $instance;
    }
}