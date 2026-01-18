<?php

namespace AssistantEngine\OpenAI\Contracts;

interface ResponseOutputItem extends HydratableInterface
{
    public function getType(): string;
}