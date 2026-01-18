<?php

namespace AssistantEngine\OpenAI\Types\Responses\Input;

use AssistantEngine\OpenAI\Contracts\HydratableInterface;
use AssistantEngine\OpenAI\Types\Responses\Input\Message\MessageInputFile;
use AssistantEngine\OpenAI\Types\Responses\Input\Message\MessageInputImage;
use AssistantEngine\OpenAI\Types\Responses\Input\Message\MessageInputText;

class MessageInput implements HydratableInterface
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_INCOMPLETE = 'incomplete';

    public string $id;

    /**
     * A list of one or many input items to the model, containing different content types.
     *
     * @var array
     */
    public array $content;

    /**
     * The role of the message input. One of `user`, `system`, or `developer`.
     *
     * @var string
     */
    public string $role;

    /**
     * The status of the message. One of `in_progress`, `completed`, or `incomplete`.
     *
     * @var string|null
     */
    public ?string $status = null;

    /**
     * The type of the message input. Always set to `message`.
     *
     * @var string
     */
    public string $type;

    public function __construct()
    {
        $this->content = [];
        $this->type = 'message';
    }

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = $data['id'] ?? '';
        $instance->role = $data['role'] ?? 'user';
        $instance->status = $data['status'] ?? null;
        $instance->type = $data['type'] ?? 'message';
        $instance->content = [];

        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $contentItem) {
                if (!isset($contentItem['type'])) {
                    throw new \Exception("Content item missing type.");
                }

                $instance->content[] = match ($contentItem['type']) {
                    'input_text' => MessageInputText::fromArray($contentItem),
                    'input_image' => MessageInputImage::fromArray($contentItem),
                    'input_file' => MessageInputFile::fromArray($contentItem),
                    default => throw new \Exception("Unknown input content type: " . $contentItem['type']),
                };
            }
        }

        return $instance;
    }
}