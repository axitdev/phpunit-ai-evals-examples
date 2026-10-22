<?php

declare(strict_types=1);

namespace App;

use OpenAI\Contracts\ClientContract;

/**
 * Structured-output classifier built on openai-php/client.
 */
final class SentimentClassifier
{
    private const SYSTEM = 'Classify the sentiment of the product review. '
        . 'Reply with only a JSON object: {"sentiment": "positive" | "negative" | "neutral", "confidence": number between 0 and 1}.';

    public function __construct(private readonly ClientContract $openAi, private readonly string $model) {}

    /**
     * Returns the model's raw JSON answer, so callers (and tests) can validate its shape.
     */
    public function classify(string $review): string
    {
        $response = $this->openAi->chat()->create([
            'model' => $this->model,
            'temperature' => 0,
            'messages' => [['role' => 'system', 'content' => self::SYSTEM], ['role' => 'user', 'content' => $review]],
        ]);
        $content = trim((string) $response->choices[0]->message->content);

        // Some models wrap JSON in a markdown fence.
        return (string) preg_replace('/^```(?:json)?\s*|\s*```$/', '', $content);
    }
}
