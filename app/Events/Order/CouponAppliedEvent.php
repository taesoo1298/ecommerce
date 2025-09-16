<?php

namespace App\Events\Order;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CouponAppliedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var float
     */
    public $discountAmount;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, float $discountAmount)
    {
        $this->order = $order;
        $this->discountAmount = $discountAmount;
    }
}
