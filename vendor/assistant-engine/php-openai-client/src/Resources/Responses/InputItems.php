<?php

namespace AssistantEngine\OpenAI\Resources\Responses;

use GuzzleHttp\Client;
use AssistantEngine\OpenAI\Types\Responses\InputItemsListResponse;

class InputItems
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * List input items.
     *
     * @param string $id The response ID.
     * @param array  $params Optional query parameters:
     *                       - after: string, an item ID to list items after.
     *                       - before: string, an item ID to list items before.
     *                       - limit: int, a limit on the number of objects to be returned (defaults to 20).
     *                       - order: string, the order to return the items in (asc or desc, default is asc).
     *
     * @return InputItemsListResponse
     */
    public function list(string $id, array $params = []): InputItemsListResponse
    {
        // Optional: set defaults if not provided
        if (!isset($params['limit'])) {
            $params['limit'] = 20;
        }
        if (!isset($params['order'])) {
            $params['order'] = 'asc';
        }

        $response = $this->client->get('responses/' . $id . '/input_items', [
            'query' => $params,
        ]);
        $data = json_decode($response->getBody()->getContents(), true);

        return InputItemsListResponse::fromArray($data);
    }
}