<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_allowed_transitions(): void
    {
        $this->assertTrue(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Paid));
        $this->assertTrue(OrderStatus::Processing->canTransitionTo(OrderStatus::Shipped));
        $this->assertFalse(OrderStatus::PendingPayment->canTransitionTo(OrderStatus::Shipped));
        $this->assertFalse(OrderStatus::Shipped->canTransitionTo(OrderStatus::Cancelled));
        $this->assertSame([], OrderStatus::Completed->nextStatuses());
    }

    public function test_rupiah_format(): void
    {
        $this->assertSame('Rp150.000', Money::rupiah(150000));
        $this->assertSame('Rp0', Money::rupiah(null));
    }
}
