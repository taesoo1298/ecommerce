<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 고객 ID 가져오기
        $customerIds = Customer::pluck('id')->toArray();

        if (empty($customerIds)) {
            $this->command->info('고객 데이터가 없습니다. 먼저 CustomerSeeder를 실행해주세요.');
            return;
        }

        // 상품 ID 가져오기
        $productIds = Product::where('is_active', true)
                             ->where('stock', '>', 0)
                             ->pluck('id')
                             ->toArray();

        if (empty($productIds)) {
            $this->command->info('상품 데이터가 없습니다. 먼저 ProductSeeder를 실행해주세요.');
            return;
        }

        // 여러 상태의 주문 생성
        $this->createCompletedOrders($customerIds, $productIds, 15);
        $this->createPendingOrders($customerIds, $productIds, 5);
        $this->createProcessingOrders($customerIds, $productIds, 3);
        $this->createFailedOrders($customerIds, $productIds, 2);
    }

    /**
     * 완료된 주문 생성
     */
    private function createCompletedOrders($customerIds, $productIds, $count)
    {
        for ($i = 0; $i < $count; $i++) {
            $customerId = fake()->randomElement($customerIds);

            $order = Order::factory()->completed()->create([
                'customer_id' => $customerId,
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            ]);

            $this->createOrderItems($order->id, $productIds);
            $order->recalculateTotal();
        }
    }

    /**
     * 진행 중인 주문 생성
     */
    private function createPendingOrders($customerIds, $productIds, $count)
    {
        for ($i = 0; $i < $count; $i++) {
            $customerId = fake()->randomElement($customerIds);

            $order = Order::factory()->pending()->create([
                'customer_id' => $customerId,
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            ]);

            $this->createOrderItems($order->id, $productIds);
            $order->recalculateTotal();
        }
    }

    /**
     * 처리 중인 주문 생성
     */
    private function createProcessingOrders($customerIds, $productIds, $count)
    {
        for ($i = 0; $i < $count; $i++) {
            $customerId = fake()->randomElement($customerIds);

            $order = Order::factory()->processing()->create([
                'customer_id' => $customerId,
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            ]);

            $this->createOrderItems($order->id, $productIds);
            $order->recalculateTotal();
        }
    }

    /**
     * 실패한 주문 생성
     */
    private function createFailedOrders($customerIds, $productIds, $count)
    {
        for ($i = 0; $i < $count; $i++) {
            $customerId = fake()->randomElement($customerIds);

            $order = Order::factory()->failed()->create([
                'customer_id' => $customerId,
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'failure_reason' => fake()->randomElement([
                    '결제 처리 중 오류가 발생했습니다.',
                    '재고 부족으로 주문을 처리할 수 없습니다.',
                    '고객 정보 검증에 실패했습니다.',
                    '시스템 오류로 주문이 취소되었습니다.'
                ])
            ]);

            $this->createOrderItems($order->id, $productIds);
            $order->recalculateTotal();
        }
    }

    /**
     * 주문 상품 생성
     */
    private function createOrderItems($orderId, $productIds)
    {
        // 주문당 1~4개의 상품 추가
        $itemCount = fake()->numberBetween(1, 4);

        // 중복 없이 상품 ID 선택
        $selectedProductIds = fake()->randomElements($productIds, $itemCount);

        foreach ($selectedProductIds as $productId) {
            $product = Product::find($productId);
            $quantity = fake()->numberBetween(1, 3);

            OrderItem::factory()->create([
                'order_id' => $orderId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $product->price,
                'total' => $product->price * $quantity,
            ]);
        }
    }
}
