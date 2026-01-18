<?php

namespace AssistantEngine\OpenAI\Types\Responses;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;
use AssistantEngine\OpenAI\Contracts\ResponseOutputItem;
use AssistantEngine\OpenAI\Contracts\Tool;
use AssistantEngine\OpenAI\Factories\ResponseOutputItemFactory;
use AssistantEngine\OpenAI\Factories\ToolFactory;
use AssistantEngine\OpenAI\Types\Responses\Output\MessageOutput;

class Response implements HydratableInterface {
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_INCOMPLETE = 'incomplete';

    public string $id;
    public float $created_at;
    public ?ResponseError $error;
    public ?IncompleteDetails $incomplete_details;
    public ?string $instructions;
    public array $metadata;
    public string $model;
    public string $object;
    /** @var ResponseOutputItem[] */
    public array $output;
    public bool $parallel_tool_calls;
    public ?float $temperature;
    public $tool_choice; // Could be a string or an object, depending on your spec
    /** @var Tool[] */
    public array $tools;
    public ?float $top_p;
    public ?int $max_output_tokens;
    public ?string $previous_response_id;
    public ?Reasoning $reasoning;
    public ?string $status;
    public ?ResponseTextConfig $text;
    public ?string $truncation;
    public ?ResponseUsage $usage;
    public ?string $user;
    public bool $store = true;

    public string $rawJSON = '';

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->created_at = isset($data['created_at']) ? (float)$data['created_at'] : 0;
        $instance->error = isset($data['error']) ? ResponseError::fromArray($data['error']) : null;
        $instance->id = $data['id'] ?? '';
        $instance->incomplete_details = isset($data['incomplete_details'])
            ? IncompleteDetails::fromArray($data['incomplete_details'])
            : null;
        $instance->instructions = $data['instructions'] ?? null;
        $instance->max_output_tokens = $data['max_output_tokens'] ?? null;
        $instance->metadata = $data['metadata'] ?? [];
        $instance->model = $data['model'] ?? '';
        $instance->object = $data['object'] ?? 'response';
        $instance->output = [];
        if (isset($data['output']) && is_array($data['output'])) {
            foreach ($data['output'] as $item) {
                $instance->output[] = ResponseOutputItemFactory::fromArray($item);
            }
        }
        $instance->parallel_tool_calls = $data['parallel_tool_calls'] ?? false;
        $instance->previous_response_id = $data['previous_response_id'] ?? null;
        $instance->reasoning = isset($data['reasoning']) ? Reasoning::fromArray($data['reasoning']) : null;
        $instance->status = $data['status'] ?? null;
        $instance->temperature = $data['temperature'] ?? null;
        $instance->text = isset($data['text']) ? ResponseTextConfig::fromArray($data['text']) : null;
        $instance->tool_choice = $data['tool_choice'] ?? null;

        $instance->tools = [];
        if (isset($data['tools']) && is_array($data['tools'])) {
            foreach ($data['tools'] as $tool) {
                $instance->tools[] = ToolFactory::fromArray($tool);
            }
        }


        $instance->top_p = $data['top_p'] ?? null;
        $instance->truncation = $data['truncation'] ?? null;
        $instance->usage = isset($data['usage']) ? ResponseUsage::fromArray($data['usage']) : null;
        $instance->user = $data['user'] ?? null;
        $instance->store = $data['store'] ?? true;
        $instance->rawJSON = json_encode($data);

        return $instance;
    }

    /**
     * Aggregates all output_text from the message-type output items.
     *
     * @return string The concatenated text from all output_text items.
     */
    public function getOutputText(): string {
        $texts = [];
        foreach ($this->output as $output) {
            // Check if the output item is a message
            if ($output instanceof MessageOutput) {
                // Expecting a MessageOutput instance with a 'content' property.
                foreach ($output->content as $content) {
                    // Check if the content item is of type 'output_text'
                    if (isset($content->type) && $content->type === "output_text") {
                        $texts[] = $content->text;
                    }
                }
            }
        }
        return implode("", $texts);
    }
}