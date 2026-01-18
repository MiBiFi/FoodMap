<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output;

use AssistantEngine\OpenAI\Contracts\ResponseOutputItem;
use AssistantEngine\OpenAI\Factories\ActionFactory;
use AssistantEngine\OpenAI\Types\Responses\Computer\SafetyCheck;

class ComputerToolCall implements ResponseOutputItem
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_INCOMPLETE  = 'incomplete';

    public string $id;
    public string $call_id;
    public string $status;
    public string $type; // must always be "computer_call"
    /** @var object The action object (one of the action classes below) */
    public object $action;
    /** @var SafetyCheck[] */
    public array $pending_safety_checks;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->id = $data['id'] ?? '';
        $instance->call_id = $data['call_id'] ?? '';
        $instance->status = $data['status'] ?? '';
        $instance->type = $data['type'] ?? 'computer_call';

        if (isset($data['action']) && is_array($data['action'])) {
            $instance->action = ActionFactory::fromArray($data['action']);
        } else {
            throw new \Exception('Missing action data for computer tool call.');
        }

        $instance->pending_safety_checks = [];
        if (isset($data['pending_safety_checks']) && is_array($data['pending_safety_checks'])) {
            foreach ($data['pending_safety_checks'] as $checkData) {
                $instance->pending_safety_checks[] = SafetyCheck::fromArray($checkData);
            }
        }
        return $instance;
    }

    public function getType(): string {
        return $this->type;
    }
}