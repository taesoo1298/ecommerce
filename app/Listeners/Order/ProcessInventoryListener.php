<?php

namespace App\Listeners\Order;

use App\Events\Order\CouponAppliedEvent;
use App\Events\Order\InventoryDeductedEvent;
use App\Events\Order\InventoryFailedEvent;
use App\Models\OrderEvent;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessInventoryListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(CouponAppliedEvent $event): void
    {
        $order = $event->order;

        // 주문 이벤트 기록
        $orderEvent = OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'inventory_processing',
            'payload' => [
                'order_id' => $order->id,
                'items' => $order->items->toArray(),
            ],
        ]);

        try {
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
                event(new InventoryFailedEvent($order, $errorMessage));
                return;
            }

            // 모든 재고 처리가 성공했을 경우
            DB::commit();

            // 재고 처리 완료 표시
            $order->markInventoryProcessed();
            $orderEvent->markProcessed();

            // 재고 차감 완료 이벤트 발행
            event(new InventoryDeductedEvent($order));

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('재고 처리 중 오류 발생: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'exception' => $e,
            ]);

            $orderEvent->markFailed($e->getMessage());
            event(new InventoryFailedEvent($order, $e->getMessage()));
        }
    }
}
