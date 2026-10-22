<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

/**
 * A small tool-calling support agent speaking the OpenAI Chat Completions protocol over any PSR-18 client.
 */
final class RefundAgent
{
    private const SYSTEM = 'You are a customer support agent for an online shop. Always look up an order before acting on it. '
        . 'Refund only orders that exist and were delivered damaged or not at all. Reply to the customer warmly and politely in one or two short sentences, '
        . 'and never promise anything the tools did not confirm, such as timelines.';

    private const TOOLS = [
        ['type' => 'function', 'function' => [
            'name' => 'lookup_order',
            'description' => 'Look up an order by its numeric id.',
            'parameters' => ['type' => 'object', 'properties' => ['order_id' => ['type' => 'integer']], 'required' => ['order_id']],
        ]],
        ['type' => 'function', 'function' => [
            'name' => 'issue_refund',
            'description' => 'Refund an order in full.',
            'parameters' => [
                'type' => 'object',
                'properties' => ['order_id' => ['type' => 'integer'], 'reason' => ['type' => 'string']],
                'required' => ['order_id', 'reason'],
            ],
        ]],
    ];

    public function __construct(
        private readonly ClientInterface $http,
        private readonly OrderBook $orders,
        private readonly string $baseUri,
        private readonly string $model,
        private readonly string $apiKey,
    ) {}

    public function handle(string $customerMessage): string
    {
        $messages = [['role' => 'system', 'content' => self::SYSTEM], ['role' => 'user', 'content' => $customerMessage]];

        for ($turn = 0; $turn < 6; $turn++) {
            $message = $this->complete($messages);
            $calls = $message['tool_calls'] ?? [];
            if ($calls === []) {
                return trim((string) ($message['content'] ?? ''));
            }

            $messages[] = ['role' => 'assistant', 'content' => $message['content'] ?? '', 'tool_calls' => $calls];
            foreach ($calls as $call) {
                $args = json_decode($call['function']['arguments'], true) ?: [];
                $result = match ($call['function']['name']) {
                    'lookup_order' => $this->orders->lookup((int) ($args['order_id'] ?? 0)),
                    'issue_refund' => $this->orders->refund((int) ($args['order_id'] ?? 0), (string) ($args['reason'] ?? '')),
                    default => ['error' => 'Unknown tool.'],
                };
                $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'] ?? '', 'content' => json_encode($result)];
            }
        }

        throw new RuntimeException('The agent did not finish within 6 turns.');
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return array<string, mixed>
     */
    private function complete(array $messages): array
    {
        $response = $this->http->sendRequest(new Request(
            'POST',
            rtrim($this->baseUri, '/') . '/chat/completions',
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->apiKey],
            json_encode(['model' => $this->model, 'temperature' => 0, 'messages' => $messages, 'tools' => self::TOOLS], JSON_THROW_ON_ERROR),
        ));
        $data = json_decode((string) $response->getBody(), true);
        if ($response->getStatusCode() !== 200 || !is_array($data)) {
            throw new RuntimeException('LLM request failed: ' . $response->getStatusCode() . ' ' . $response->getBody());
        }

        return $data['choices'][0]['message'];
    }
}
