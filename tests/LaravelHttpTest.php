<?php

declare(strict_types=1);

namespace App\Tests;

use App\Translator;
use Axitdev\PhpUnitAiEvals\Assertions\AiAssertionsTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Client\Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Laravel's HTTP client is Guzzle-based, not PSR-18. A global Guzzle middleware hands every request to the VCR.
 * In a Laravel app, call Http::globalMiddleware(...) the same way in setUp().
 */
final class LaravelHttpTest extends TestCase
{
    use AiAssertionsTrait;

    public function testTranslatesToGerman(): void
    {
        $vcr = $this->wrapHttpClient(new Client());
        $http = new Factory();
        $http->globalMiddleware(static fn(callable $next): callable => static fn(RequestInterface $request, array $options) => Create::promiseFor($vcr->sendRequest($request)));

        $translator = new Translator($http, (string) getenv('LLM_BASE_URI'), (string) getenv('LLM_MODEL'), (string) getenv('LLM_API_KEY'));
        $german = $translator->toGerman('The library opens at nine in the morning.');

        self::assertStringContainsStringIgnoringCase('Bibliothek', $german);
        self::assertCount(1, $this->getAllAiInteractions());
        $this->assertMatchesRubric('Is a correct German translation of "The library opens at nine in the morning."', $german);
    }
}
