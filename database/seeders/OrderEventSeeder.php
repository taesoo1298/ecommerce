<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Database\Seeder;

class OrderEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 기존 주문 ID 가져오기
        $orderIds = Order::pluck('id')->toArray();

        if (empty($orderIds)) {
            $this->command->info('주문 데이터가 없습니다. 먼저 OrderSeeder를 실행해주세요.');
            return;
        }

        // 각 주문에 대한 이벤트 생성
        foreach ($orderIds as $orderId) {
            $order = Order::find($orderId);

            // 주문 생성 이벤트 (모든 주문에 포함)
            OrderEvent::factory()->processed()->create([
                'order_id' => $orderId,
                'event_type' => 'order_created',
                'payload' => [
                    'customer_id' => $order->customer_id,
                    'total_items' => $order->getTotalItems(),
                    'total_amount' => $order->total,
                ],
                'created_at' => $order->created_at,
                'processed_at' => $order->created_at,
            ]);

            // 주문 상태에 따른 이벤트 생성
            switch ($order->status) {
                case 'completed':
                    $this->createCompletedOrderEvents($order);
                    break;
                case 'processing':
                    $this->createProcessingOrderEvents($order);
                    break;
                case 'failed':
                    $this->createFailedOrderEvents($order);
                    break;
                case 'pending':
                    // 주문 생성 이벤트만 있음
                    break;
            }
        }
    }

    /**
     * 완료된 주문에 대한 이벤트 생성
     */
    private function createCompletedOrderEvents($order)
    {
        // 쿠폰 처리 이벤트
        OrderEvent::factory()->processed()->create([
            'order_id' => $order->id,
            'event_type' => 'coupon_processing',
            'payload' => [
                'coupon_code' => fake()->boolean(30) ? strtoupper(fake()->bothify('???###')) : null,
                'discount_amount' => $order->discount,
            ],
            'created_at' => $order->coupon_processed_at,
            'processed_at' => $order->coupon_processed_at,
        ]);

        // 재고 처리 이벤트
        OrderEvent::factory()->processed()->create([
            'order_id' => $order->id,
            'event_type' => 'inventory_processing',
            'payload' => [
                'items' => $order->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                    ];
                })->toArray(),
            ],
            'created_at' => $order->inventory_processed_at,
            'processed_at' => $order->inventory_processed_at,
        ]);

        // 결제 처리 이벤트
        OrderEvent::factory()->processed()->create([
            'order_id' => $order->id,
            'event_type' => 'payment_processing',
            'payload' => [
                'payment_method' => fake()->randomElement(['card', 'bank', 'virtual_account', 'mobile']),
                'amount' => $order->total,
                'payment_id' => fake()->uuid(),
            ],
            'created_at' => $order->payment_processed_at,
            'processed_at' => $order->payment_processed_at,
        ]);

        // 알림 처리 이벤트
        OrderEvent::factory()->processed()->create([
            'order_id' => $order->id,
            'event_type' => 'notification_processing',
            'payload' => [
                'email' => $order->customer->email,
                'phone' => $order->customer->phone,
                'notification_types' => ['email', $order->customer->phone ? 'sms' : null],
            ],
            'created_at' => $order->notification_sent_at,
            'processed_at' => $order->notification_sent_at,
        ]);

        // 주문 완료 이벤트
        OrderEvent::factory()->processed()->create([
            'order_id' => $order->id,
            'event_type' => 'order_completed',
            'payload' => [
                'completed_at' => $order->updated_at->toIso8601String(),
            ],
            'created_at' => $order->updated_at,
            'processed_at' => $order->updated_at,
        ]);
    }

    /**
     * 처리 중인 주문에 대한 이벤트 생성
     */
    private function createProcessingOrderEvents($order)
    {
        // 쿠폰 처리 이벤트
        if ($order->coupon_processed_at) {
            OrderEvent::factory()->processed()->create([
                'order_id' => $order->id,
                'event_type' => 'coupon_processing',
                'payload' => [
                    'coupon_code' => fake()->boolean(30) ? strtoupper(fake()->bothify('???###')) : null,
                    'discount_amount' => $order->discount,
                ],
                'created_at' => $order->coupon_processed_at,
                'processed_at' => $order->coupon_processed_at,
            ]);
        }

        // 재고 처리 이벤트
        if ($order->inventory_processed_at) {
            OrderEvent::factory()->processed()->create([
                'order_id' => $order->id,
                'event_type' => 'inventory_processing',
                'payload' => [
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                        ];
                    })->toArray(),
                ],
                'created_at' => $order->inventory_processed_at,
                'processed_at' => $order->inventory_processed_at,
            ]);
        }

        // 결제 처리 이벤트 (대기 중)
        if (!$order->payment_processed_at) {
            OrderEvent::factory()->pending()->create([
                'order_id' => $order->id,
                'event_type' => 'payment_processing',
                'payload' => [
                    'payment_method' => fake()->randomElement(['card', 'bank', 'virtual_account', 'mobile']),
                    'amount' => $order->total,
                ],
                'created_at' => now(),
            ]);
        }
    }

    /**
     * 실패한 주문에 대한 이벤트 생성
     */
    private function createFailedOrderEvents($order)
    {
        // 쿠폰 처리 이벤트 (성공)
        OrderEvent::factory()->processed()->create([
            'order_id' => $order->id,
            'event_type' => 'coupon_processing',
            'payload' => [
                'coupon_code' => fake()->boolean(30) ? strtoupper(fake()->bothify('???###')) : null,
                'discount_amount' => $order->discount,
            ],
            'created_at' => now()->subMinutes(fake()->numberBetween(30, 60)),
            'processed_at' => now()->subMinutes(fake()->numberBetween(30, 60)),
        ]);

        // 실패 지점 랜덤 결정
        $failurePoint = fake()->randomElement(['inventory', 'payment', 'notification']);

        if ($failurePoint === 'inventory') {
            // 재고 처리 이벤트 (실패)
            OrderEvent::factory()->failed()->create([
                'order_id' => $order->id,
                'event_type' => 'inventory_processing',
                'payload' => [
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                        ];
                    })->toArray(),
                ],
                'error_message' => '재고 부족으로 주문을 처리할 수 없습니다.',
                'created_at' => now()->subMinutes(fake()->numberBetween(10, 40)),
                'processed_at' => now()->subMinutes(fake()->numberBetween(10, 40)),
            ]);
        } else {
            // 재고 처리 이벤트 (성공)
            OrderEvent::factory()->processed()->create([
                'order_id' => $order->id,
                'event_type' => 'inventory_processing',
                'payload' => [
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                        ];
                    })->toArray(),
                ],
                'created_at' => now()->subMinutes(fake()->numberBetween(20, 50)),
                'processed_at' => now()->subMinutes(fake()->numberBetween(20, 50)),
            ]);

            if ($failurePoint === 'payment') {
                // 결제 처리 이벤트 (실패)
                OrderEvent::factory()->failed()->create([
                    'order_id' => $order->id,
                    'event_type' => 'payment_processing',
                    'payload' => [
                        'payment_method' => fake()->randomElement(['card', 'bank', 'virtual_account', 'mobile']),
                        'amount' => $order->total,
                    ],
                    'error_message' => '결제 처리 중 오류가 발생했습니다.',
                    'created_at' => now()->subMinutes(fake()->numberBetween(5, 30)),
                    'processed_at' => now()->subMinutes(fake()->numberBetween(5, 30)),
                ]);
            } else {
                // 결제 처리 이벤트 (성공)
                OrderEvent::factory()->processed()->create([
                    'order_id' => $order->id,
                    'event_type' => 'payment_processing',
                    'payload' => [
                        'payment_method' => fake()->randomElement(['card', 'bank', 'virtual_account', 'mobile']),
                        'amount' => $order->total,
                        'payment_id' => fake()->uuid(),
                    ],
                    'created_at' => now()->subMinutes(fake()->numberBetween(10, 40)),
                    'processed_at' => now()->subMinutes(fake()->numberBetween(10, 40)),
                ]);

                // 알림 처리 이벤트 (실패)
                OrderEvent::factory()->failed()->create([
                    'order_id' => $order->id,
                    'event_type' => 'notification_processing',
                    'payload' => [
                        'email' => $order->customer->email,
                        'phone' => $order->customer->phone,
                        'notification_types' => ['email', $order->customer->phone ? 'sms' : null],
                    ],
                    'error_message' => '알림 전송 중 오류가 발생했습니다.',
                    'created_at' => now()->subMinutes(fake()->numberBetween(1, 20)),
                    'processed_at' => now()->subMinutes(fake()->numberBetween(1, 20)),
                ]);
            }
        }

        // 주문 실패 이벤트
        OrderEvent::factory()->processed()->create([
            'order_id' => $order->id,
            'event_type' => 'order_failed',
            'payload' => [
                'failed_at' => $order->updated_at->toIso8601String(),
                'reason' => $order->failure_reason,
            ],
            'created_at' => $order->updated_at,
            'processed_at' => $order->updated_at,
        ]);
    }
}
