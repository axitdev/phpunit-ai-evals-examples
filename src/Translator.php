<?php

declare(strict_types=1);

namespace App;

use Illuminate\Http\Client\Factory;

/**
 * Uses Laravel's HTTP client (what the `Http` facade resolves to, and what Prism uses under the hood).
 */
final class Translator
{
    public function __construct(
        private readonly Factory $http,
        private readonly string $baseUri,
        private readonly string $model,
        private readonly string $apiKey,
    ) {}

    public function toGerman(string $text): string
    {
        return trim((string) $this->http->withToken($this->apiKey)
            ->post(rtrim($this->baseUri, '/') . '/chat/completions', [
                'model' => $this->model,
                'temperature' => 0,
                'messages' => [
                    ['role' => 'system', 'content' => 'Translate the user text to German. Reply with the translation only.'],
                    ['role' => 'user', 'content' => $text],
                ],
            ])
            ->throw()
            ->json('choices.0.message.content'));
    }
}
