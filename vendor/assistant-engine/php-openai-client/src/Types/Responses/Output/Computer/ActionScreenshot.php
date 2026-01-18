<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Computer;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class ActionScreenshot implements HydratableInterface
{
    public string $type = 'screenshot';

    public static function fromArray(array $data): self {
        // No extra properties for a screenshot action.
        return new self();
    }
}