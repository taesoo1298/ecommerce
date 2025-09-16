<?php

namespace App\Adapters;

use App\Events\Order\CouponAppliedEvent;
use App\Events\Order\CouponFailedEvent;
use App\Events\Order\InventoryDeductedEvent;
use App\Events\Order\InventoryFailedEvent;
use App\Events\Order\OrderCreatedEvent;
use App\Events\Order\PaymentCompletedEvent;
use App\Events\Order\PaymentFailedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Kafka\OrderEventPublisherService;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Laravel 이벤트와 Kafka 이벤트 연결 어댑터
 */
class KafkaEventAdapter
{
    /**
     * @var OrderEventPublisherService
     */
    private $publisher;

    /**
     * 생성자
     *
     * @param OrderEventPublisherService $publisher
     */
    public function __construct(OrderEventPublisherService $publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * 이벤트 리스너 등록
     *
     * @param Dispatcher $events
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        // 주문 생성 이벤트
        $events->listen(
            OrderCreatedEvent::class,
            [$this, 'handleOrderCreated']
        );

        // 쿠폰 처리 이벤트
        $events->listen(
            CouponAppliedEvent::class,
            [$this, 'handleCouponApplied']
        );

        $events->listen(
            CouponFailedEvent::class,
            [$this, 'handleCouponFailed']
        );

        // 재고 처리 이벤트
        $events->listen(
            InventoryDeductedEvent::class,
            [$this, 'handleInventoryDeducted']
        );

        $events->listen(
            InventoryFailedEvent::class,
            [$this, 'handleInventoryFailed']
        );

        // 결제 처리 이벤트
        $events->listen(
            PaymentCompletedEvent::class,
            [$this, 'handlePaymentCompleted']
        );

        $events->listen(
            PaymentFailedEvent::class,
            [$this, 'handlePaymentFailed']
        );
    }

    /**
     * 주문 생성 이벤트 처리
     *
     * @param OrderCreatedEvent $event
     * @return void
     */
    public function handleOrderCreated(OrderCreatedEvent $event): void
    {
        try {
            $this->publisher->publishOrderCreated($event->order);
        } catch (\Exception $e) {
            Log::error('Kafka 주문 생성 이벤트 발행 실패', [
                'order_id' => $event->order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 쿠폰 적용 이벤트 처리
     *
     * @param CouponAppliedEvent $event
     * @return void
     */
    public function handleCouponApplied(CouponAppliedEvent $event): void
    {
        try {
            $this->publisher->publishCouponApplied($event->order, $event->discountAmount);
        } catch (\Exception $e) {
            Log::error('Kafka 쿠폰 적용 이벤트 발행 실패', [
                'order_id' => $event->order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 쿠폰 적용 실패 이벤트 처리
     *
     * @param CouponFailedEvent $event
     * @return void
     */
    public function handleCouponFailed(CouponFailedEvent $event): void
    {
        try {
            $this->publisher->publishCouponFailed($event->order, $event->errorMessage);
        } catch (\Exception $e) {
            Log::error('Kafka 쿠폰 실패 이벤트 발행 실패', [
                'order_id' => $event->order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 재고 차감 이벤트 처리
     *
     * @param InventoryDeductedEvent $event
     * @return void
     */
    public function handleInventoryDeducted(InventoryDeductedEvent $event): void
    {
        try {
            $this->publisher->publishInventoryDeducted($event->order);
        } catch (\Exception $e) {
            Log::error('Kafka 재고 차감 이벤트 발행 실패', [
                'order_id' => $event->order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 재고 차감 실패 이벤트 처리
     *
     * @param InventoryFailedEvent $event
     * @return void
     */
    public function handleInventoryFailed(InventoryFailedEvent $event): void
    {
        try {
            $this->publisher->publishInventoryFailed($event->order, $event->errorMessage);
        } catch (\Exception $e) {
            Log::error('Kafka 재고 실패 이벤트 발행 실패', [
                'order_id' => $event->order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 결제 완료 이벤트 처리
     *
     * @param PaymentCompletedEvent $event
     * @return void
     */
    public function handlePaymentCompleted(PaymentCompletedEvent $event): void
    {
        try {
            $this->publisher->publishPaymentCompleted($event->order, $event->payment);
        } catch (\Exception $e) {
            Log::error('Kafka 결제 완료 이벤트 발행 실패', [
                'order_id' => $event->order->id,
                'payment_id' => $event->payment->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 결제 실패 이벤트 처리
     *
     * @param PaymentFailedEvent $event
     * @return void
     */
    public function handlePaymentFailed(PaymentFailedEvent $event): void
    {
        try {
            $this->publisher->publishPaymentFailed($event->order, $event->errorMessage);
        } catch (\Exception $e) {
            Log::error('Kafka 결제 실패 이벤트 발행 실패', [
                'order_id' => $event->order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
