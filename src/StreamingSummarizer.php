<?php

declare(strict_types=1);

namespace App;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Summarises text through Ollama's native streaming /api/chat endpoint (newline-delimited JSON).
 */
final class StreamingSummarizer
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly string $ollamaUri,
        private readonly string $model,
    ) {}

    public function summarize(string $text): string
    {
        $request = $this->requests->createRequest('POST', rtrim($this->ollamaUri, '/') . '/api/chat')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streams->createStream(json_encode([
                'model' => $this->model,
                'stream' => true,
                'options' => ['temperature' => 0],
                'messages' => [
                    ['role' => 'system', 'content' => 'Summarise the text in one sentence of at most 25 words.'],
                    ['role' => 'user', 'content' => $text],
                ],
            ], JSON_THROW_ON_ERROR)));

        $summary = '';
        foreach (explode("\n", (string) $this->http->sendRequest($request)->getBody()) as $line) {
            $chunk = json_decode($line, true);
            $summary .= is_array($chunk) ? ($chunk['message']['content'] ?? '') : '';
        }

        return trim($summary);
    }
}
