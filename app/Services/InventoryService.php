<?php

namespace App\Services;

use App\Events\Order\InventoryDeductedEvent;
use App\Events\Order\InventoryFailedEvent;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /**
     * 재고 처리
     *
     * @param int $orderId
     * @return array
     */
    public function processInventory(int $orderId): array
    {
        try {
            $order = Order::with('items')->findOrFail($orderId);

            // 이미 재고가 처리된 주문인 경우
            if ($order->isInventoryProcessed()) {
                return [
                    'success' => true,
                    'message' => '이미 재고가 처리된 주문입니다.',
                ];
            }

            // 주문 이벤트 기록
            $orderEvent = OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'inventory_processing',
                'payload' => [
                    'order_id' => $order->id,
                    'items' => $order->items->toArray(),
                ],
            ]);

            // 재고 처리를 위한 트랜잭션 시작
            DB::beginTransaction();

            $stockErrors = [];

            // 주문 아이템 순회하며 재고 차감
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);

                if (!$product) {
                    $stockErrors[] = "상품 ID {$item->product_id}를 찾을 수 없습니다.";
                    continue;
                }

                // 재고 확인 및 차감
                if (!$product->decreaseStock($item->quantity)) {
                    $stockErrors[] = "상품 '{$product->name}'의 재고가 부족합니다. 요청: {$item->quantity}, 보유: {$product->stock}";
                }
            }

            // 재고 부족 등의 문제가 있는 경우
            if (!empty($stockErrors)) {
                DB::rollBack();
                $errorMessage = implode(', ', $stockErrors);
                $orderEvent->markFailed($errorMessage);

                // 이벤트 발행
                event(new InventoryFailedEvent($order, $errorMessage));

                return [
                    'success' => false,
                    'message' => $errorMessage,
                ];
            }

            // 모든 재고 처리가 성공했을 경우
            DB::commit();

            // 재고 처리 완료 표시
            $order->markInventoryProcessed();
            $orderEvent->markProcessed();

            // 재고 차감 완료 이벤트 발행
            event(new InventoryDeductedEvent($order));

            return [
                'success' => true,
                'message' => '재고가 성공적으로 차감되었습니다.',
            ];

        } catch (\Exception $e) {
            // 트랜잭션 롤백
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('재고 처리 중 오류 발생: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => '재고 처리 중 오류가 발생했습니다: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 재고 복구
     *
     * @param int $orderId
     * @return array
     */
    public function restoreInventory(int $orderId): array
    {
        try {
            $order = Order::with('items')->findOrFail($orderId);

            // 이미 재고가 처리되지 않은 주문인 경우
            if (!$order->isInventoryProcessed()) {
                return [
                    'success' => true,
                    'message' => '재고 차감이 처리되지 않은 주문입니다.',
                ];
            }

            // 주문 이벤트 기록
            $orderEvent = OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'inventory_restoring',
                'payload' => [
                    'order_id' => $order->id,
                    'items' => $order->items->toArray(),
                ],
            ]);

            // 재고 복구를 위한 트랜잭션 시작
            DB::beginTransaction();

            $restoreErrors = [];

            // 주문 아이템 순회하며 재고 복구
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);

                if (!$product) {
                    $restoreErrors[] = "상품 ID {$item->product_id}를 찾을 수 없습니다.";
                    continue;
                }

                // 재고 복구
                $product->increaseStock($item->quantity);
            }

            // 재고 복구 문제가 있는 경우
            if (!empty($restoreErrors)) {
                DB::rollBack();
                $errorMessage = implode(', ', $restoreErrors);
                $orderEvent->markFailed($errorMessage);

                return [
                    'success' => false,
                    'message' => $errorMessage,
                ];
            }

            // 모든 재고 복구가 성공했을 경우
            DB::commit();

            // 재고 처리 상태 업데이트
            $order->inventory_processed_at = null;
            $order->save();
            $orderEvent->markProcessed();

            return [
                'success' => true,
                'message' => '재고가 성공적으로 복구되었습니다.',
            ];

        } catch (\Exception $e) {
            // 트랜잭션 롤백
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('재고 복구 중 오류 발생: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => '재고 복구 중 오류가 발생했습니다: ' . $e->getMessage(),
            ];
        }
    }
}
