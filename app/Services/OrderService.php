<?php

namespace App\Services;

use App\Events\Order\OrderCreatedEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * 주문 생성
     *
     * @param array $orderData
     * @return Order|null
     */
    public function createOrder(array $orderData): ?Order
    {
        try {
            // 트랜잭션 시작
            DB::beginTransaction();

            // 주문 번호 생성
            $orderNumber = $this->generateOrderNumber();

            // 상품 아이템 추출
            $items = $orderData['items'] ?? [];

            // 주문 합계 계산
            $subtotal = 0;
            $tax = 0;

            // 아이템 검증 및 합계 계산
            $orderItems = [];
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                if (!$product->is_active) {
                    throw new \Exception("상품 '{$product->name}'은(는) 현재 구매할 수 없습니다.");
                }

                $quantity = $item['quantity'] ?? 1;
                $price = $product->price;
                $total = $price * $quantity;

                $subtotal += $total;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $total,
                ];
            }

            // 세금 계산 (예: 10%)
            $tax = round($subtotal * 0.1, 2);

            // 최종 합계
            $total = $subtotal + $tax;

            // 주문 생성
            $order = Order::create([
                'customer_id' => $orderData['customer_id'],
                'order_number' => $orderNumber,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => 0, // 할인은 쿠폰 처리 단계에서 적용
                'total' => $total,
                'status' => 'pending',
                'notes' => $orderData['notes'] ?? null,
                'coupon_code' => $orderData['coupon_code'] ?? null,
                'payment_method' => $orderData['payment_method'] ?? 'card',
            ]);

            // 주문 아이템 생성
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total'],
                ]);
            }

            // 트랜잭션 커밋
            DB::commit();

            // 주문 생성 이벤트 발행
            event(new OrderCreatedEvent($order));

            return $order;

        } catch (\Exception $e) {
            // 트랜잭션 롤백
            DB::rollBack();

            Log::error('주문 생성 실패: ' . $e->getMessage(), [
                'order_data' => $orderData,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * 주문 번호 생성
     *
     * @return string
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'ORD-';
        $timestamp = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return $prefix . $timestamp . '-' . $random;
    }

    /**
     * 주문 정보 조회
     *
     * @param int $orderId
     * @return Order|null
     */
    public function getOrder(int $orderId): ?Order
    {
        return Order::with(['items.product', 'customer', 'payments'])->find($orderId);
    }

    /**
     * 주문 취소
     *
     * @param int $orderId
     * @return bool
     */
    public function cancelOrder(int $orderId): bool
    {
        try {
            $order = Order::findOrFail($orderId);

            // 이미 완료된 주문은 취소 불가
            if ($order->isCompleted()) {
                throw new \Exception('이미 완료된 주문은 취소할 수 없습니다.');
            }

            // 주문 상태 업데이트
            $order->status = 'cancelled';
            $order->save();

            // 재고 복구 (이미 재고가 차감된 경우)
            if ($order->isInventoryProcessed()) {
                foreach ($order->items as $item) {
                    $product = $item->product;
                    $product->increaseStock($item->quantity);
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('주문 취소 실패: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e,
            ]);

            return false;
        }
    }
}
