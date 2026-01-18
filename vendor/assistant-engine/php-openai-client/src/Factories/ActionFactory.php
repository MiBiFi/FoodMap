<?php

namespace AssistantEngine\OpenAI\Factories;

use AssistantEngine\OpenAI\Types\Responses\Output\Computer\ActionClick;
use AssistantEngine\OpenAI\Types\Responses\Output\Computer\ActionDoubleClick;
use AssistantEngine\OpenAI\Types\Responses\Output\Computer\ActionDrag;
use AssistantEngine\OpenAI\Types\Responses\Output\Computer\ActionKeypress;
use AssistantEngine\OpenAI\Types\Responses\Output\Computer\ActionMove;
use AssistantEngine\OpenAI\Types\Responses\Output\Computer\ActionScreenshot;
use AssistantEngine\OpenAI\Types\Responses\Output\Computer\ActionScroll;
use AssistantEngine\OpenAI\Types\Responses\Output\Computer\ActionType;
use AssistantEngine\OpenAI\Types\Responses\Output\Computer\ActionWait;

class ActionFactory
{
    /**
     * Factory method to create an action instance based on the "type" in the data.
     *
     * @param array $data The input data containing the "type" field.
     * @return object One of the action instances (ActionClick, ActionDoubleClick, etc.).
     * @throws \Exception If the action type is unknown.
     */
    public static function fromArray(array $data): object {
        $actionType = $data['type'] ?? '';
        return match ($actionType) {
            'click'         => ActionClick::fromArray($data),
            'double_click'  => ActionDoubleClick::fromArray($data),
            'drag'          => ActionDrag::fromArray($data),
            'keypress'      => ActionKeypress::fromArray($data),
            'move'          => ActionMove::fromArray($data),
            'screenshot'    => ActionScreenshot::fromArray($data),
            'scroll'        => ActionScroll::fromArray($data),
            'type'          => ActionType::fromArray($data),
            'wait'          => ActionWait::fromArray($data),
            default         => throw new \Exception('Unknown action type: ' . $actionType),
        };
    }
}