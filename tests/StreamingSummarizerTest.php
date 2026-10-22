<?php

declare(strict_types=1);

namespace App\Tests;

use App\StreamingSummarizer;
use Axitdev\PhpUnitAiEvals\Assertions\AiAssertionsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * Symfony HttpClient (through its PSR-18 adapter) and a streamed Ollama response, recorded and replayed as NDJSON.
 */
final class StreamingSummarizerTest extends TestCase
{
    use AiAssertionsTrait;

    private const ARTICLE = <<<'TXT'
        The city council approved a plan on Tuesday to convert the old tram depot on Mill Street into a public library.
        Construction starts next spring and is expected to take eighteen months. The project costs 4.2 million euros,
        most of it covered by a regional culture grant. The library will include a children's wing and a rooftop reading garden.
        TXT;

    public function testSummaryIsShortAndGrounded(): void
    {
        // Psr18Client is also a PSR-17 request and stream factory.
        $psr18 = new Psr18Client();
        $summarizer = new StreamingSummarizer($this->wrapHttpClient($psr18), $psr18, $psr18, (string) getenv('OLLAMA_URI'), (string) getenv('LLM_MODEL'));

        $summary = $summarizer->summarize(self::ARTICLE);

        self::assertLessThanOrEqual(30, str_word_count($summary));
        self::assertTokenUsageLessThan(1500);
        $this->assertNoHallucination([self::ARTICLE], $summary);
        $this->assertMatchesRubric('Mentions that a library will be built in the old tram depot', $summary);
    }
}
