<?php

namespace AssistantEngine\OpenAI\Types\Responses\Tool;

use AssistantEngine\OpenAI\Contracts\Tool;
use AssistantEngine\OpenAI\Types\Responses\Tool\WebSearch\UserLocation;

class WebSearchTool implements Tool
{
    /**
     * The type of the web search tool.
     * One of "web_search_preview" or "web_search_preview_2025_03_11".
     */
    public string $type;

    /**
     * High-level guidance for the amount of context window space to use.
     * One of "low", "medium", or "high". (Default is "medium".)
     */
    public ?string $search_context_size = null;

    /**
     * The user location information.
     */
    public ?UserLocation $user_location = null;

    public function __construct()
    {
        // Set a default type if none is provided.
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->type = $data['type'] ?? 'web_search_preview';
        $instance->search_context_size = $data['search_context_size'] ?? null;

        if (isset($data['user_location']) && is_array($data['user_location'])) {
            $instance->user_location = UserLocation::fromArray($data['user_location']);
        }

        return $instance;
    }

    public function getType(): string
    {
        return $this->type;
    }
}