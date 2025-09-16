<?php

namespace App\Listeners\Order;

use App\Events\Order\OrderCreatedEvent;
use App\Models\Coupon;
use App\Models\OrderEvent;
use App\Events\Order\CouponAppliedEvent;
use App\Events\Order\CouponFailedEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessCouponListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCreatedEvent $event): void
    {
        $order = $event->order;

        // 주문 이벤트 기록
        $orderEvent = OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'coupon_processing',
            'payload' => [
                'order_id' => $order->id,
                'coupon_code' => $order->coupon_code ?? null,
            ],
        ]);

        try {
            // 쿠폰 코드가 없는 경우
            if (empty($order->coupon_code)) {
                // 쿠폰이 없어도 성공으로 처리하고 다음 단계로 진행
                $orderEvent->markProcessed('skipped');
                $order->markCouponProcessed();
                event(new CouponAppliedEvent($order, 0));
                return;
            }

            // 쿠폰 검색
            $coupon = Coupon::where('code', $order->coupon_code)->first();

            // 쿠폰이 존재하지 않는 경우
            if (!$coupon) {
                $errorMessage = "쿠폰 코드 '{$order->coupon_code}'를 찾을 수 없습니다.";
                $orderEvent->markFailed($errorMessage);
                event(new CouponFailedEvent($order, $errorMessage));
                return;
            }

            // 쿠폰 유효성 검사
            if (!$coupon->isValid($order->subtotal)) {
                $errorMessage = "쿠폰 '{$order->coupon_code}'이 유효하지 않습니다.";
                $orderEvent->markFailed($errorMessage);
                event(new CouponFailedEvent($order, $errorMessage));
                return;
            }

            // 할인 금액 계산
            $discountAmount = $coupon->calculateDiscount($order->subtotal);

            // 쿠폰 사용 횟수 증가
            $coupon->incrementUsage();

            // 주문 금액 업데이트
            $order->discount = $discountAmount;
            $order->total = $order->subtotal + $order->tax - $order->discount;
            $order->save();

            // 쿠폰 처리 완료 표시
            $order->markCouponProcessed();
            $orderEvent->markProcessed();

            // 쿠폰 적용 이벤트 발행
            event(new CouponAppliedEvent($order, $discountAmount));

        } catch (\Exception $e) {
            Log::error('쿠폰 처리 중 오류 발생: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'coupon_code' => $order->coupon_code ?? null,
                'exception' => $e,
            ]);

            $orderEvent->markFailed($e->getMessage());
            event(new CouponFailedEvent($order, $e->getMessage()));
        }
    }
}
