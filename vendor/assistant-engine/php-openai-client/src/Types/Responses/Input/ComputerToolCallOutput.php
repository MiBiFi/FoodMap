<?php

namespace AssistantEngine\OpenAI\Types\Responses\Input;

use AssistantEngine\OpenAI\Types\Responses\Computer\SafetyCheck;
use AssistantEngine\OpenAI\Types\Responses\Input\Computer\Output;

class ComputerToolCallOutput  implements ResponseOutputItem
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_INCOMPLETE  = 'incomplete';

    /**
     * The unique ID of the computer tool call output.
     *
     * @var string
     */
    public string $id;

    /**
     * The ID of the computer tool call that produced the output.
     *
     * @var string
     */
    public string $call_id;

    /**
     * The status of the output.
     * One of `in_progress`, `completed`, or `incomplete`.
     *
     * @var string
     */
    public string $status;

    /**
     * The type of the output.
     * Always set to `computer_call_output`.
     *
     * @var string
     */
    public string $type;

    /**
     * The output object produced by the computer tool call.
     *
     * @var Output
     */
    public Output $output;

    /**
     * An array of acknowledged pending safety checks.
     *
     * @var SafetyCheck[]
     */
    public array $acknowledged_safety_checks;

    public function __construct()
    {
        $this->type = 'computer_call_output';
        $this->acknowledged_safety_checks = [];
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = $data['id'] ?? '';
        $instance->call_id = $data['call_id'] ?? '';
        $instance->status = $data['status'] ?? '';
        $instance->type = $data['type'] ?? 'computer_call_output';

        if (!isset($data['output']) || !is_array($data['output'])) {
            throw new \Exception('Missing or invalid output data for computer tool call output.');
        }
        $instance->output = Output::fromArray($data['output']);

        $instance->acknowledged_safety_checks = [];
        if (isset($data['acknowledged_safety_checks']) && is_array($data['acknowledged_safety_checks'])) {
            foreach ($data['acknowledged_safety_checks'] as $checkData) {
                // Assuming PendingSafetyCheck implements fromArray() and exists in the correct namespace.
                $instance->acknowledged_safety_checks[] = SafetyCheck::fromArray($checkData);
            }
        }

        return $instance;
    }

    public function getType(): string
    {
        return $this->type;
    }
}