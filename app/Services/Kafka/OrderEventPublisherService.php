<?php

namespace App\Services\Kafka;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class OrderEventPublisherService
{
    /**
     * @var KafkaProducerService
     */
    private $producer;

    /**
     * OrderEventPublisherService 생성자
     *
     * @param KafkaProducerService $producer
     */
    public function __construct(KafkaProducerService $producer)
    {
        $this->producer = $producer;
    }

    /**
     * 주문 생성 이벤트 발행
     *
     * @param Order $order
     * @return bool
     */
    public function publishOrderCreated(Order $order): bool
    {
        return $this->publishEvent('order.created', $order);
    }

    /**
     * 쿠폰 적용 이벤트 발행
     *
     * @param Order $order
     * @param float $discountAmount
     * @return bool
     */
    public function publishCouponApplied(Order $order, float $discountAmount): bool
    {
        return $this->publishEvent('order.coupon.applied', [
            'order_id' => $order->id,
            'discount_amount' => $discountAmount,
            'order_total' => $order->total,
        ]);
    }

    /**
     * 쿠폰 적용 실패 이벤트 발행
     *
     * @param Order $order
     * @param string $errorMessage
     * @return bool
     */
    public function publishCouponFailed(Order $order, string $errorMessage): bool
    {
        return $this->publishEvent('order.coupon.failed', [
            'order_id' => $order->id,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * 재고 차감 이벤트 발행
     *
     * @param Order $order
     * @return bool
     */
    public function publishInventoryDeducted(Order $order): bool
    {
        return $this->publishEvent('order.inventory.deducted', [
            'order_id' => $order->id,
            'items' => $order->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ];
            })->toArray(),
        ]);
    }

    /**
     * 재고 차감 실패 이벤트 발행
     *
     * @param Order $order
     * @param string $errorMessage
     * @return bool
     */
    public function publishInventoryFailed(Order $order, string $errorMessage): bool
    {
        return $this->publishEvent('order.inventory.failed', [
            'order_id' => $order->id,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * 결제 완료 이벤트 발행
     *
     * @param Order $order
     * @param Payment $payment
     * @return bool
     */
    public function publishPaymentCompleted(Order $order, Payment $payment): bool
    {
        return $this->publishEvent('order.payment.completed', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
        ]);
    }

    /**
     * 결제 실패 이벤트 발행
     *
     * @param Order $order
     * @param string $errorMessage
     * @return bool
     */
    public function publishPaymentFailed(Order $order, string $errorMessage): bool
    {
        return $this->publishEvent('order.payment.failed', [
            'order_id' => $order->id,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * 일반 이벤트 발행 메서드
     *
     * @param string $topic
     * @param mixed $data
     * @return bool
     */
    private function publishEvent(string $topic, $data): bool
    {
        try {
            $eventData = $this->prepareEventData($data);

            // 이벤트 로깅
            $this->logEvent($topic, $eventData);

            // Kafka로 메시지 발행
            $result = $this->producer->publish($topic, $eventData);

            // 버퍼 메시지 처리 보장
            $this->producer->flush(1000);

            return $result;
        } catch (\Exception $e) {
            Log::error('이벤트 발행 실패', [
                'topic' => $topic,
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 이벤트 데이터 준비
     *
     * @param mixed $data
     * @return array
     */
    private function prepareEventData($data): array
    {
        // Order 객체인 경우 필요한 데이터 추출
        if ($data instanceof Order) {
            return [
                'order_id' => $data->id,
                'order_number' => $data->order_number,
                'customer_id' => $data->customer_id,
                'subtotal' => $data->subtotal,
                'tax' => $data->tax,
                'discount' => $data->discount,
                'total' => $data->total,
                'status' => $data->status,
                'created_at' => $data->created_at->toIso8601String(),
                'items' => $data->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'total' => $item->total,
                    ];
                })->toArray(),
            ];
        }

        // 이미 배열인 경우 그대로 반환
        if (is_array($data)) {
            return array_merge($data, [
                'event_time' => now()->toIso8601String(),
                'event_id' => uniqid('evt_', true),
            ]);
        }

        // 그 외 데이터 타입은 직렬화하여 반환
        return [
            'data' => $data,
            'event_time' => now()->toIso8601String(),
            'event_id' => uniqid('evt_', true),
        ];
    }

    /**
     * 이벤트 로깅
     *
     * @param string $topic
     * @param array $data
     * @return void
     */
    private function logEvent(string $topic, array $data): void
    {
        // 주문 ID 추출
        $orderId = $data['order_id'] ?? null;

        if ($orderId) {
            // DB에 이벤트 로깅
            OrderEvent::create([
                'order_id' => $orderId,
                'event_type' => $topic,
                'payload' => $data,
                'status' => 'published',
            ]);
        }

        // 로그에도 기록
        Log::info('이벤트 발행', [
            'topic' => $topic,
            'order_id' => $orderId,
        ]);
    }
}
