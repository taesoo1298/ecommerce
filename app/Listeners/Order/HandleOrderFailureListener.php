<?php

namespace App\Listeners\Order;

use App\Events\Order\CouponFailedEvent;
use App\Events\Order\InventoryFailedEvent;
use App\Events\Order\PaymentFailedEvent;
use App\Models\OrderEvent;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleOrderFailureListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * @var NotificationService
     */
    protected $notificationService;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the payment failed event.
     */
    public function handlePaymentFailed(PaymentFailedEvent $event): void
    {
        $this->processFailure($event->order, '결제 처리 실패', $event->errorMessage, 'payment_failed');
    }

    /**
     * Handle the inventory failed event.
     */
    public function handleInventoryFailed(InventoryFailedEvent $event): void
    {
        $this->processFailure($event->order, '재고 처리 실패', $event->errorMessage, 'inventory_failed');
    }

    /**
     * Handle the coupon failed event.
     */
    public function handleCouponFailed(CouponFailedEvent $event): void
    {
        $this->processFailure($event->order, '쿠폰 처리 실패', $event->errorMessage, 'coupon_failed');
    }

    /**
     * 공통 실패 처리 로직
     */
    private function processFailure($order, $failureType, $errorMessage, $eventType): void
    {
        Log::warning("{$failureType}: {$errorMessage}", [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);

        // 주문 이벤트 기록
        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => $eventType,
            'payload' => [
                'order_id' => $order->id,
                'error_message' => $errorMessage,
                'failed_at' => now()->toIso8601String(),
            ],
            'is_processed' => true,
            'status' => 'failed',
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);

        // 주문 실패 처리
        $order->fail($errorMessage);

        // 고객에게 실패 알림 전송 (SMS 또는 이메일)
        $this->sendFailureNotification($order, $failureType, $errorMessage);
    }

    /**
     * 실패 알림 전송
     */
    private function sendFailureNotification($order, $failureType, $errorMessage): void
    {
        try {
            // NotificationService를 통해 알림 전송
            $result = $this->notificationService->sendFailureNotification($order, $failureType, $errorMessage);

            Log::info("주문 실패 알림 전송됨", [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'failure_type' => $failureType,
                'email_result' => $result['email']['success'],
                'sms_result' => $result['sms']['success'],
            ]);
        } catch (\Exception $e) {
            Log::error("주문 실패 알림 전송 중 오류 발생", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
