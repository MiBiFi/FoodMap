<?php

namespace AssistantEngine\OpenAI\Types\Responses\Output\Message;

use AssistantEngine\OpenAI\Contracts\ResponseOutputItem;

class ResponseOutputRefusal  implements ResponseOutputItem
{
    public string $type = 'refusal';
    public string $refusal;

    public static function fromArray(array $data): self {
        $instance = new self();
        $instance->refusal = $data['refusal'] ?? '';
        return $instance;
    }

    public function getType(): string {
        return $this->type;
    }
}