<?php

declare(strict_types=1);

namespace App\Tests;

use App\SentimentClassifier;
use Axitdev\PhpUnitAiEvals\Assertions\AiAssertionsTrait;
use Axitdev\PhpUnitAiEvals\Dataset;
use GuzzleHttp\Client;
use OpenAI;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * openai-php/client wired through the VCR, evaluated over a dataset.
 */
final class SentimentClassifierTest extends TestCase
{
    use AiAssertionsTrait;

    private const SCHEMA = [
        'type' => 'object',
        'required' => ['sentiment', 'confidence'],
        'properties' => [
            'sentiment' => ['enum' => ['positive', 'negative', 'neutral']],
            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
        ],
    ];

    private function classifier(): SentimentClassifier
    {
        $openAi = OpenAI::factory()
            ->withBaseUri((string) getenv('LLM_BASE_URI'))
            ->withApiKey((string) getenv('LLM_API_KEY'))
            ->withHttpClient($this->wrapHttpClient(new Client()))
            ->make();

        return new SentimentClassifier($openAi, (string) getenv('LLM_MODEL'));
    }

    /**
     * Suite-level bar: the classifier may miss a case, but must get at least 80% right.
     */
    public function testAccuracyOnTheReviewDataset(): void
    {
        $classifier = $this->classifier();

        $this->assertPassRate(Dataset::load(__DIR__ . '/datasets/reviews.jsonl'), 0.8, function (array $case) use ($classifier): void {
            $json = $classifier->classify($case['review']);

            self::assertMatchesJsonSchema(self::SCHEMA, $json);
            self::assertSame($case['expected'], json_decode($json, true)['sentiment']);
        }, 'sentiment accuracy');
    }

    /**
     * The same dataset as one PHPUnit test per case: every answer must be well-formed JSON.
     *
     * @param array{id: string, review: string, expected: string} $case
     */
    #[DataProvider('reviews')]
    public function testEveryAnswerMatchesTheSchema(array $case): void
    {
        self::assertMatchesJsonSchema(self::SCHEMA, $this->classifier()->classify($case['review']));
    }

    /**
     * @return array<int|string, array{array<string, mixed>}>
     */
    public static function reviews(): array
    {
        return Dataset::provide(__DIR__ . '/datasets/reviews.jsonl');
    }
}
