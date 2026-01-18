<?php

namespace AssistantEngine\OpenAI\Factories;

use AssistantEngine\OpenAI\Types\Responses\Input\ComputerToolCallOutput;
use AssistantEngine\OpenAI\Types\Responses\Input\FunctionToolCallOutput;
use AssistantEngine\OpenAI\Types\Responses\Input\MessageInput;
use AssistantEngine\OpenAI\Types\Responses\Output\MessageOutput;

class InputItemListFactory
{
    /**
     * Creates an input item instance from an array.
     *
     * This factory handles items that are either part of the response’s input
     * (e.g. user messages) or part of its output (e.g. assistant messages and other
     * output types). For a "message" type, it distinguishes based on the "role".
     * For other types (like file search, function call, web search, computer call, or reasoning)
     * it delegates to the ResponseOutputItemFactory.
     *
     * @param array $data The raw data for the input item.
     * @return object An instance of the appropriate input or output class.
     * @throws \Exception if the type is unknown.
     */
    public static function fromArray(array $data): object {
        $type = $data['type'] ?? '';

        switch ($type) {
            case 'message':
                // Check the role to determine if this message is an input or an output.
                // If the role is 'assistant', we treat it as an output message.
                if (isset($data['role']) && $data['role'] === 'assistant') {
                    return MessageOutput::fromArray($data);
                } else {
                    return MessageInput::fromArray($data);
                }
            case 'computer_call_output':
                return ComputerToolCallOutput::fromArray($data);
            case 'function_call_output':
                return FunctionToolCallOutput::fromArray($data);
            // For types available in the ResponseOutputItemFactory, delegate.
            case 'file_search_call':
            case 'function_call':
            case 'web_search_call':
            case 'computer_call':
            case 'reasoning':
                return ResponseOutputItemFactory::fromArray($data);
            default:
                throw new \Exception("Unknown input item type: " . $type);
        }
    }
}