<?php

namespace App\Listeners\Order;

use App\Events\Order\PaymentCompletedEvent;
use App\Models\OrderEvent;
use App\Models\Notification as NotificationModel;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOrderNotificationListener implements ShouldQueue
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
     * Handle the event.
     */
    public function handle(PaymentCompletedEvent $event): void
    {
        $order = $event->order;
        $customer = $order->customer;

        // 주문 이벤트 기록
        $orderEvent = OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'notification_processing',
            'payload' => [
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'email' => $customer->email,
            ],
        ]);

        try {
            // 이메일 알림 전송
            $emailSent = $this->sendEmailNotification($order, $customer);

            // SMS 알림 전송 (고객이 전화번호가 있는 경우)
            $smsSent = false;
            if ($customer->phone) {
                $smsSent = $this->sendSmsNotification($order, $customer);
            }

            // 알림 전송 완료 표시
            $order->markNotificationSent();
            $orderEvent->markProcessed();

            // 주문 완료 처리
            $order->complete();

        } catch (\Exception $e) {
            Log::error('알림 전송 중 오류 발생: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'exception' => $e,
            ]);

            $orderEvent->markFailed($e->getMessage());
        }
    }

    /**
     * 이메일 알림 전송
     */
    private function sendEmailNotification($order, $customer): bool
    {
        try {
            // 이메일 내용 생성
            $subject = "주문 확인 #{$order->order_number}";
            $content = $this->buildEmailContent($order);

            // 알림 서비스로 이메일 전송
            $result = $this->notificationService->sendEmail($customer->email, $subject, $content);

            // 알림 로그 저장
            NotificationModel::create([
                'order_id' => $order->id,
                'type' => 'email',
                'recipient' => $customer->email,
                'subject' => $subject,
                'content' => $content,
                'is_sent' => $result['success'],
                'sent_at' => $result['success'] ? now() : null,
                'error' => $result['success'] ? null : $result['message'],
            ]);

            return $result['success'];
        } catch (\Exception $e) {
            Log::error('이메일 알림 전송 실패', [
                'order_id' => $order->id,
                'email' => $customer->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * SMS 알림 전송
     */
    private function sendSmsNotification($order, $customer): bool
    {
        try {
            // SMS 내용 생성
            $content = "주문이 완료되었습니다. 주문번호: {$order->order_number}, 금액: {$order->total}원";

            // 알림 서비스로 SMS 전송
            $result = $this->notificationService->sendSms($customer->phone, $content);

            // 알림 로그 저장
            NotificationModel::create([
                'order_id' => $order->id,
                'type' => 'sms',
                'recipient' => $customer->phone,
                'subject' => null,
                'content' => $content,
                'is_sent' => $result['success'],
                'sent_at' => $result['success'] ? now() : null,
                'error' => $result['success'] ? null : $result['message'],
            ]);

            return $result['success'];
        } catch (\Exception $e) {
            Log::error('SMS 알림 전송 실패', [
                'order_id' => $order->id,
                'phone' => $customer->phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 이메일 내용 생성
     */
    private function buildEmailContent($order): string
    {
        // 실제 구현에서는 이메일 템플릿 사용
        $content = "안녕하세요, {$order->customer->name}님.\n\n";
        $content .= "주문이 성공적으로 완료되었습니다.\n\n";
        $content .= "주문 번호: {$order->order_number}\n";
        $content .= "주문 날짜: {$order->created_at->format('Y-m-d H:i')}\n";
        $content .= "결제 금액: {$order->total}원\n\n";

        $content .= "주문 상품:\n";
        foreach ($order->items as $item) {
            $content .= "- {$item->product->name}: {$item->quantity}개, {$item->price}원/개, 소계: {$item->total}원\n";
        }

        $content .= "\n감사합니다.";

        return $content;
    }
}
