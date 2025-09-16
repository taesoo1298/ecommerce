<?php

namespace App\Services;

use App\Events\Order\PaymentCompletedEvent;
use App\Events\Order\PaymentFailedEvent;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * @var PaymentGatewayService
     */
    private $paymentGateway;

    /**
     * PaymentService 생성자
     *
     * @param PaymentGatewayService $paymentGateway
     */
    public function __construct(PaymentGatewayService $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    /**
     * 결제 처리
     *
     * @param int $orderId
     * @return array
     */
    public function processPayment(int $orderId): array
    {
        try {
            $order = Order::findOrFail($orderId);

            // 이미 결제가 처리된 주문인 경우
            if ($order->isPaymentProcessed()) {
                return [
                    'success' => true,
                    'message' => '이미 결제가 처리된 주문입니다.',
                    'amount' => $order->total,
                ];
            }

            // 주문 이벤트 기록
            $orderEvent = OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'payment_processing',
                'payload' => [
                    'order_id' => $order->id,
                    'amount' => $order->total,
                ],
            ]);

            // 결제 처리
            $paymentResult = $this->paymentGateway->processPayment([
                'amount' => $order->total,
                'currency' => 'KRW',
                'order_id' => $order->order_number,
                'customer_id' => $order->customer_id,
                'payment_method' => $order->payment_method ?? 'card',
            ]);

            // 결제 실패 시
            if (!$paymentResult['success']) {
                $errorMessage = $paymentResult['message'] ?? '결제 처리 실패';
                $orderEvent->markFailed($errorMessage);

                // 이벤트 발행
                event(new PaymentFailedEvent($order, $errorMessage));

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'amount' => $order->total,
                ];
            }

            // 결제 정보 저장
            $payment = Payment::create([
                'order_id' => $order->id,
                'transaction_id' => $paymentResult['transaction_id'],
                'payment_method' => $order->payment_method ?? 'card',
                'amount' => $order->total,
                'currency' => 'KRW',
                'status' => 'completed',
                'payment_details' => json_encode($paymentResult['details'] ?? []),
            ]);

            // 결제 처리 완료 표시
            $order->markPaymentProcessed();
            $orderEvent->markProcessed();

            // 결제 완료 이벤트 발행
            event(new PaymentCompletedEvent($order, $payment));

            return [
                'success' => true,
                'message' => '결제가 성공적으로 처리되었습니다.',
                'amount' => $order->total,
                'transaction_id' => $payment->transaction_id,
            ];

        } catch (\Exception $e) {
            Log::error('결제 처리 중 오류 발생: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => '결제 처리 중 오류가 발생했습니다: ' . $e->getMessage(),
                'amount' => 0,
            ];
        }
    }

    /**
     * 결제 취소
     *
     * @param int $orderId
     * @return array
     */
    public function refundPayment(int $orderId): array
    {
        try {
            $order = Order::with('payments')->findOrFail($orderId);

            // 결제되지 않은 주문인 경우
            if (!$order->isPaymentProcessed()) {
                return [
                    'success' => false,
                    'message' => '결제되지 않은 주문은 환불할 수 없습니다.',
                ];
            }

            // 이미 환불된 주문인 경우
            if ($order->status === 'refunded') {
                return [
                    'success' => true,
                    'message' => '이미 환불된 주문입니다.',
                ];
            }

            // 결제 정보 확인
            $payment = $order->payments()->where('status', 'completed')->latest()->first();

            if (!$payment) {
                return [
                    'success' => false,
                    'message' => '환불할 결제 정보를 찾을 수 없습니다.',
                ];
            }

            // 주문 이벤트 기록
            $orderEvent = OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'payment_refunding',
                'payload' => [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                ],
            ]);

            // 결제 취소 처리
            $refundResult = $this->paymentGateway->refundPayment(
                $payment->transaction_id,
                $payment->amount
            );

            // 환불 실패 시
            if (!$refundResult['success']) {
                $orderEvent->markFailed($refundResult['message']);

                return [
                    'success' => false,
                    'message' => $refundResult['message'],
                ];
            }

            // 환불 정보 저장
            Payment::create([
                'order_id' => $order->id,
                'transaction_id' => $refundResult['refund_id'],
                'payment_method' => $payment->payment_method,
                'amount' => -$payment->amount, // 음수로 저장하여 환불 표시
                'currency' => $payment->currency,
                'status' => 'refunded',
                'payment_details' => json_encode($refundResult['details'] ?? []),
            ]);

            // 주문 상태 업데이트
            $order->status = 'refunded';
            $order->save();

            $orderEvent->markProcessed();

            return [
                'success' => true,
                'message' => '결제가 성공적으로 취소되었습니다.',
                'refund_id' => $refundResult['refund_id'],
            ];

        } catch (\Exception $e) {
            Log::error('결제 취소 중 오류 발생: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => '결제 취소 중 오류가 발생했습니다: ' . $e->getMessage(),
            ];
        }
    }
}
