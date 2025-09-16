<?php

namespace App\Services;

use App\Events\Order\CouponAppliedEvent;
use App\Events\Order\CouponFailedEvent;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Support\Facades\Log;

class CouponService
{
    /**
     * 쿠폰 처리
     *
     * @param int $orderId
     * @return array
     */
    public function processCoupon(int $orderId): array
    {
        try {
            $order = Order::findOrFail($orderId);

            // 이미 쿠폰이 처리된 주문인 경우
            if ($order->isCouponProcessed()) {
                return [
                    'success' => true,
                    'message' => '이미 쿠폰이 처리된 주문입니다.',
                    'discount_amount' => $order->discount,
                ];
            }

            // 주문 이벤트 기록
            $orderEvent = OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'coupon_processing',
                'payload' => [
                    'order_id' => $order->id,
                    'coupon_code' => $order->coupon_code ?? null,
                ],
            ]);

            // 쿠폰 코드가 없는 경우
            if (empty($order->coupon_code)) {
                // 쿠폰이 없어도 성공으로 처리하고 다음 단계로 진행
                $orderEvent->markProcessed('skipped');
                $order->markCouponProcessed();

                // 이벤트 발행
                event(new CouponAppliedEvent($order, 0));

                return [
                    'success' => true,
                    'message' => '적용된 쿠폰이 없습니다.',
                    'discount_amount' => 0,
                ];
            }

            // 쿠폰 검색
            $coupon = Coupon::where('code', $order->coupon_code)->first();

            // 쿠폰이 존재하지 않는 경우
            if (!$coupon) {
                $errorMessage = "쿠폰 코드 '{$order->coupon_code}'를 찾을 수 없습니다.";
                $orderEvent->markFailed($errorMessage);

                // 이벤트 발행
                event(new CouponFailedEvent($order, $errorMessage));

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'discount_amount' => 0,
                ];
            }

            // 쿠폰 유효성 검사
            if (!$coupon->isValid($order->subtotal)) {
                $errorMessage = "쿠폰 '{$order->coupon_code}'이 유효하지 않습니다.";
                $orderEvent->markFailed($errorMessage);

                // 이벤트 발행
                event(new CouponFailedEvent($order, $errorMessage));

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'discount_amount' => 0,
                ];
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

            return [
                'success' => true,
                'message' => '쿠폰이 성공적으로 적용되었습니다.',
                'discount_amount' => $discountAmount,
            ];

        } catch (\Exception $e) {
            Log::error('쿠폰 처리 중 오류 발생: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => '쿠폰 처리 중 오류가 발생했습니다: ' . $e->getMessage(),
                'discount_amount' => 0,
            ];
        }
    }

    /**
     * 쿠폰 검증
     *
     * @param string $couponCode
     * @param float $orderAmount
     * @return array
     */
    public function validateCoupon(string $couponCode, float $orderAmount): array
    {
        try {
            // 쿠폰 검색
            $coupon = Coupon::where('code', $couponCode)->first();

            if (!$coupon) {
                return [
                    'valid' => false,
                    'message' => '존재하지 않는 쿠폰 코드입니다.',
                ];
            }

            // 쿠폰 유효성 검사
            if (!$coupon->isValid($orderAmount)) {
                if ($coupon->isExpired()) {
                    return [
                        'valid' => false,
                        'message' => '만료된 쿠폰입니다.',
                    ];
                }

                if ($coupon->min_order_amount > $orderAmount) {
                    return [
                        'valid' => false,
                        'message' => "최소 주문 금액은 {$coupon->min_order_amount}원 입니다.",
                    ];
                }

                if ($coupon->usage_count >= $coupon->max_usage) {
                    return [
                        'valid' => false,
                        'message' => '사용 횟수가 초과된 쿠폰입니다.',
                    ];
                }

                return [
                    'valid' => false,
                    'message' => '유효하지 않은 쿠폰입니다.',
                ];
            }

            // 할인 금액 계산
            $discountAmount = $coupon->calculateDiscount($orderAmount);

            return [
                'valid' => true,
                'message' => '유효한 쿠폰입니다.',
                'discount_amount' => $discountAmount,
                'coupon' => $coupon,
            ];

        } catch (\Exception $e) {
            Log::error('쿠폰 검증 중 오류 발생: ' . $e->getMessage(), [
                'coupon_code' => $couponCode,
                'order_amount' => $orderAmount,
                'exception' => $e,
            ]);

            return [
                'valid' => false,
                'message' => '쿠폰 검증 중 오류가 발생했습니다.',
            ];
        }
    }
}
