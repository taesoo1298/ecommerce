<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Order;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 알림을 보낼 수 있는 주문(완료된 주문) 가져오기
        $completedOrders = Order::where('status', 'completed')
                              ->where('notification_sent_at', '!=', null)
                              ->get();

        if ($completedOrders->isEmpty()) {
            $this->command->info('완료된 주문 데이터가 없습니다. 먼저 OrderSeeder를 실행해주세요.');
            return;
        }

        foreach ($completedOrders as $order) {
            // 이메일 알림 생성
            Notification::factory()->email()->successful()->create([
                'order_id' => $order->id,
                'recipient' => $order->customer->email,
                'subject' => "주문 확인 #{$order->order_number}",
                'content' => $this->buildEmailContent($order),
                'sent_at' => $order->notification_sent_at,
                'created_at' => $order->notification_sent_at,
                'updated_at' => $order->notification_sent_at,
            ]);

            // 고객의 전화번호가 있으면 SMS 알림도 생성
            if ($order->customer->phone) {
                Notification::factory()->sms()->successful()->create([
                    'order_id' => $order->id,
                    'recipient' => $order->customer->phone,
                    'content' => "주문이 완료되었습니다. 주문번호: {$order->order_number}, 금액: {$order->total}원",
                    'sent_at' => $order->notification_sent_at,
                    'created_at' => $order->notification_sent_at,
                    'updated_at' => $order->notification_sent_at,
                ]);
            }
        }

        // 실패한 주문에 대한 알림 (실패한 상태로)
        $failedOrders = Order::where('status', 'failed')->get();

        foreach ($failedOrders as $order) {
            // 80% 확률로 실패한 알림 생성
            if (fake()->boolean(80)) {
                Notification::factory()->email()->failed()->create([
                    'order_id' => $order->id,
                    'recipient' => $order->customer->email,
                    'subject' => "주문 처리 오류 #{$order->order_number}",
                    'content' => "주문 처리 중 오류가 발생했습니다. 주문번호: {$order->order_number}",
                    'error' => '메일 서버 연결 실패',
                    'created_at' => $order->updated_at,
                    'updated_at' => $order->updated_at,
                ]);
            }
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
