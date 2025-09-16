<?php

namespace App\Listeners\Order;

use App\Events\Order\InventoryDeductedEvent;
use App\Events\Order\PaymentCompletedEvent;
use App\Events\Order\PaymentFailedEvent;
use App\Models\OrderEvent;
use App\Models\Payment;
use App\Services\PaymentGatewayService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessPaymentListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * @var PaymentGatewayService
     */
    protected $paymentGateway;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct(PaymentGatewayService $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    /**
     * Handle the event.
     */
    public function handle(InventoryDeductedEvent $event): void
    {
        $order = $event->order;

        // 주문 이벤트 기록
        $orderEvent = OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'payment_processing',
            'payload' => [
                'order_id' => $order->id,
                'amount' => $order->total,
            ],
        ]);

        try {
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
                event(new PaymentFailedEvent($order, $errorMessage));
                return;
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

        } catch (\Exception $e) {
            Log::error('결제 처리 중 오류 발생: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'exception' => $e,
            ]);

            $orderEvent->markFailed($e->getMessage());
            event(new PaymentFailedEvent($order, $e->getMessage()));
        }
    }
}
