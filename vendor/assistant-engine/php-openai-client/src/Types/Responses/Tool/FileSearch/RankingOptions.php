<?php

namespace AssistantEngine\OpenAI\Types\Responses\Tool\FileSearch;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class RankingOptions implements HydratableInterface
{
    /**
     * The ranker to use for the file search.
     * One of "auto" or "default-2024-11-15".
     */
    public ?string $ranker = null;

    /**
     * The score threshold for the file search (0 to 1).
     * Numbers closer to 1 return only the most relevant results.
     */
    public ?float $score_threshold = null;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->ranker = $data['ranker'] ?? null;
        $instance->score_threshold = isset($data['score_threshold']) ? (float)$data['score_threshold'] : null;
        return $instance;
    }
}