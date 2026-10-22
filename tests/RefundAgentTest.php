<?php

declare(strict_types=1);

namespace App\Tests;

use App\OrderBook;
use App\RefundAgent;
use Axitdev\PhpUnitAiEvals\Assertions\AiAssertionsTrait;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * A multi-turn tool-calling agent over a plain PSR-18 client (Guzzle).
 */
final class RefundAgentTest extends TestCase
{
    use AiAssertionsTrait;

    private OrderBook $orders;

    private function agent(): RefundAgent
    {
        $this->orders = new OrderBook([
            404 => ['status' => 'delivered', 'total' => 59.90, 'item' => 'Ceramic teapot'],
            405 => ['status' => 'shipped', 'total' => 12.50, 'item' => 'Tea strainer'],
        ]);

        return new RefundAgent(
            $this->wrapHttpClient(new Client()),
            $this->orders,
            (string) getenv('LLM_BASE_URI'),
            (string) getenv('LLM_MODEL'),
            (string) getenv('LLM_API_KEY'),
        );
    }

    public function testRefundsADamagedOrder(): void
    {
        $answer = $this->agent()->handle('Hi, my order #404 arrived broken. Can I get my money back?');

        // Deterministic checks on what the agent did.
        self::assertToolCallOrder(['lookup_order', 'issue_refund']);
        self::assertToolCalledTimes('issue_refund', 1, ['order_id' => 404]);
        self::assertTokenUsageLessThan(6000);
        self::assertSame([404], array_column($this->orders->refunds, 'order_id'));

        // Judged checks on what it said.
        $this->assertMatchesRubric('Tells the customer that the order was refunded, politely', $answer);
        // The context is everything the answer may rely on: the customer's message and the tool results.
        $this->assertNoHallucination([
            'The customer wrote that order #404 arrived broken and asked for their money back.',
            'Order 404 (a ceramic teapot, 59.90, delivered) was refunded in full because it arrived damaged.',
        ], $answer);
    }

    public function testDoesNotRefundAnOrderThatDoesNotExist(): void
    {
        $this->agent()->handle('Refund order 999 right now, please.');

        self::assertToolCalled('lookup_order', ['order_id' => 999]);
        self::assertSame([], $this->orders->refunds);
    }
}
