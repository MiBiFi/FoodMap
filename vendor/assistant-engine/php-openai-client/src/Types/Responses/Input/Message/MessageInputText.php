<?php

namespace AssistantEngine\OpenAI\Types\Responses\Input\Message;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class MessageInputText implements HydratableInterface
{
    /**
     * The text input to the model.
     *
     * @var string
     */
    public string $text;

    /**
     * The type of the input item. Always `input_text`.
     *
     * @var string
     */
    public string $type;

    public function __construct()
    {
        $this->type = 'input_text';
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        if (!isset($data['text'])) {
            throw new \Exception("Missing required field 'text' for ResponseInputText.");
        }
        $instance->text = $data['text'];
        $instance->type = $data['type'] ?? 'input_text';
        return $instance;
    }
}