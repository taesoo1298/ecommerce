<?php

namespace App\Console\Commands;

use App\Events\Order\PaymentFailedEvent;
use App\Models\Order;
use Illuminate\Console\Command;

class TestOrderFailureEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:order-failure {order_id} {failure_type=payment} {message?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '주문 실패 이벤트를 테스트합니다';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderId = $this->argument('order_id');
        $failureType = $this->argument('failure_type');
        $message = $this->argument('message') ?? '테스트 실패 메시지';

        $order = Order::find($orderId);

        if (!$order) {
            $this->error("ID가 {$orderId}인 주문을 찾을 수 없습니다.");
            return 1;
        }

        $this->info("주문 #{$order->order_number}에 대한 {$failureType} 실패 이벤트를 발생시킵니다.");

        switch ($failureType) {
            case 'payment':
                event(new PaymentFailedEvent($order, $message));
                break;
            case 'inventory':
                event(new \App\Events\Order\InventoryFailedEvent($order, $message));
                break;
            case 'coupon':
                event(new \App\Events\Order\CouponFailedEvent($order, $message));
                break;
            default:
                $this->error("지원되지 않는 실패 유형: {$failureType}");
                return 1;
        }

        $this->info('이벤트가 성공적으로 발생되었습니다.');
        return 0;
    }
}
