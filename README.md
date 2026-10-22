# phpunit-ai-evals examples

> [!WARNING]
> phpunit-ai-evals is a pre-release (0.0.2) and experimental, so please don't use it in projects you depend on yet. Feedback, [issues](https://github.com/axitdev/phpunit-ai-evals/issues) and pull requests are very welcome.

Small apps that call an LLM, tested with [axitdev/phpunit-ai-evals](https://github.com/axitdev/phpunit-ai-evals). The recordings in `tests/cassettes` came from a real model, so you can run the whole suite without an API key, in well under a second:

```bash
composer install
composer test
```

## What's inside

| Test | App code | What it shows |
|---|---|---|
| [`RefundAgentTest`](tests/RefundAgentTest.php) | [`RefundAgent`](src/RefundAgent.php), a support agent with order lookup and refund tools | A multi-turn agent over Guzzle (plain PSR-18): tool order, call counts, a token budget, and rubric and hallucination judges |
| [`SentimentClassifierTest`](tests/SentimentClassifierTest.php) | [`SentimentClassifier`](src/SentimentClassifier.php), built on `openai-php/client` | A JSONL dataset that must reach an 80% pass rate, JSON Schema checks, and the same dataset as one test per case |
| [`StreamingSummarizerTest`](tests/StreamingSummarizerTest.php) | [`StreamingSummarizer`](src/StreamingSummarizer.php), using Symfony HttpClient through `Psr18Client` | A streamed Ollama response (NDJSON), recorded and replayed |
| [`LaravelHttpTest`](tests/LaravelHttpTest.php) | [`Translator`](src/Translator.php), using Laravel's `Http` client | Routing Laravel's `Http` client (which Prism uses too) through a global Guzzle middleware |

[`phpunit.xml`](phpunit.xml) registers the extension. Every run writes `build/ai-evals.json` and compares it with [`tests/ai-evals-baseline.json`](tests/ai-evals-baseline.json).

## Re-recording

The cassettes were recorded against a local [Ollama](https://ollama.com) running `gemma4:e4b`, which also acts as the judge:

```bash
ollama pull gemma4:e4b && ollama serve
AI_EVAL_MODE=record vendor/bin/phpunit
```

To record against OpenAI instead, change `LLM_BASE_URI`, `LLM_MODEL`, `LLM_API_KEY` and the `AI_EVAL_JUDGE_*` values in `phpunit.xml` (or set them in your environment). The model is part of each request's hash, so a different model always needs new recordings.

A model can answer differently each time you record, and a judge may then grade it differently. Keeping the cassettes in git means you review those changes like any other diff. After re-recording, run `AI_EVAL_PRUNE=1 vendor/bin/phpunit` to drop recordings no test uses anymore, and copy `build/ai-evals.json` over the baseline if you're happy with the scores.
