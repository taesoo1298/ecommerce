<?php

namespace App\Events\Order;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryFailedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var string
     */
    public $errorMessage;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, string $errorMessage)
    {
        $this->order = $order;
        $this->errorMessage = $errorMessage;
    }
}
