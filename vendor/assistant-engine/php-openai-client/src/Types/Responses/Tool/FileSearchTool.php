<?php

namespace AssistantEngine\OpenAI\Types\Responses\Tool;

use AssistantEngine\OpenAI\Contracts\Tool;
use AssistantEngine\OpenAI\Types\Responses\Tool\FileSearch\RankingOptions;
use AssistantEngine\OpenAI\Types\Shared\ComparisonFilter;
use AssistantEngine\OpenAI\Types\Shared\CompoundFilter;

class FileSearchTool  implements Tool
{
    /**
     * The type of the file search tool. Always "file_search".
     */
    public string $type;

    /**
     * The IDs of the vector stores to search.
     */
    public array $vector_store_ids;

    /**
     * A filter to apply based on file attributes.
     */
    public $filters = null;

    /**
     * The maximum number of results to return (between 1 and 50 inclusive).
     */
    public ?int $max_num_results = null;

    /**
     * Ranking options for search.
     */
    public ?RankingOptions $ranking_options = null;

    public function __construct() {
        $this->type = 'file_search';
        $this->vector_store_ids = [];
    }

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->type = $data['type'] ?? 'file_search';
        $instance->vector_store_ids = $data['vector_store_ids'] ?? [];

        if (isset($data['filters']) && is_array($data['filters'])) {
            if (isset($data['filters']['type'])) {
                $filterType = $data['filters']['type'];
                if (in_array($filterType, ['and', 'or'], true)) {
                    $instance->filters = CompoundFilter::fromArray($data['filters']);
                } elseif (in_array($filterType, ['eq', 'ne', 'gt', 'gte', 'lt', 'lte'], true)) {
                    $instance->filters = ComparisonFilter::fromArray($data['filters']);
                } else {
                    throw new \Exception("Unknown filter type: " . $filterType);
                }
            }
        }

        $instance->max_num_results = isset($data['max_num_results']) ? (int)$data['max_num_results'] : null;
        if (isset($data['ranking_options']) && is_array($data['ranking_options'])) {
            $instance->ranking_options = RankingOptions::fromArray($data['ranking_options']);
        }
        return $instance;
    }

    public function getType(): string {
        return $this->type;
    }
}