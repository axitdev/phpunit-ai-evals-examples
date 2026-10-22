<?php

declare(strict_types=1);

namespace App;

/**
 * In-memory stand-in for a real order system, so the agent's tools have something to act on.
 */
final class OrderBook
{
    /** @var list<array{order_id: int, reason: string}> */
    public array $refunds = [];

    /**
     * @param array<int, array{status: string, total: float, item: string}> $orders
     */
    public function __construct(private readonly array $orders) {}

    /**
     * @return array<string, mixed>
     */
    public function lookup(int $orderId): array
    {
        return isset($this->orders[$orderId])
            ? ['order_id' => $orderId, ...$this->orders[$orderId]]
            : ['error' => "Order $orderId does not exist."];
    }

    /**
     * @return array<string, mixed>
     */
    public function refund(int $orderId, string $reason): array
    {
        if (!isset($this->orders[$orderId])) {
            return ['error' => "Order $orderId does not exist."];
        }
        $this->refunds[] = ['order_id' => $orderId, 'reason' => $reason];

        return ['order_id' => $orderId, 'status' => 'refunded', 'amount' => $this->orders[$orderId]['total']];
    }
}
