<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Computer;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class ActionWait implements HydratableInterface
{
    public string $type = 'wait';

    public static function fromArray(array $data): self {
        // No additional properties for a wait action.
        return new self();
    }
}