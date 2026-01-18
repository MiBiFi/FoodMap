<?php

namespace AssistantEngine\OpenAI\Types\Responses\Input\Message;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;

class MessageInputFile implements HydratableInterface
{
    /**
     * The type of the input item. Always `input_file`.
     *
     * @var string
     */
    public string $type;

    /**
     * The content of the file to be sent to the model.
     *
     * @var string|null
     */
    public ?string $file_data = null;

    /**
     * The ID of the file to be sent to the model.
     *
     * @var string|null
     */
    public ?string $file_id = null;

    /**
     * The name of the file to be sent to the model.
     *
     * @var string|null
     */
    public ?string $filename = null;

    public function __construct()
    {
        $this->type = 'input_file';
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->type = $data['type'] ?? 'input_file';
        $instance->file_data = $data['file_data'] ?? null;
        $instance->file_id = $data['file_id'] ?? null;
        $instance->filename = $data['filename'] ?? null;
        return $instance;
    }
}