<?php

namespace App\Modules\Sales\Domain\Contracts;

final readonly class BillingData
{
    /** @param array<string,mixed> $snapshot */
    public function __construct(public OrderData $order, public array $snapshot) {}
}
