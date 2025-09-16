<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderEvent>
 */
class OrderEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = [
            'order_created',
            'coupon_processing',
            'inventory_processing',
            'payment_processing',
            'notification_processing',
            'order_completed',
            'order_failed',
        ];

        $eventType = fake()->randomElement($eventTypes);
        $isProcessed = fake()->boolean(80); // 80% 확률로 처리 완료
        $status = $isProcessed ? fake()->randomElement(['success', 'failed']) : 'pending';

        return [
            'order_id' => Order::factory(),
            'event_type' => $eventType,
            'payload' => $this->generatePayload($eventType),
            'is_processed' => $isProcessed,
            'status' => $status,
            'error_message' => $status === 'failed' ? fake()->sentence() : null,
            'processed_at' => $isProcessed ? now() : null,
        ];
    }

    /**
     * 이벤트 타입에 따른 페이로드 생성
     */
    protected function generatePayload(string $eventType): array
    {
        switch ($eventType) {
            case 'order_created':
                return [
                    'customer_id' => fake()->numberBetween(1, 100),
                    'total_items' => fake()->numberBetween(1, 10),
                    'total_amount' => fake()->randomFloat(2, 1000, 100000),
                ];
            case 'coupon_processing':
                return [
                    'coupon_code' => strtoupper(fake()->bothify('???###')),
                    'discount_amount' => fake()->randomFloat(2, 500, 5000),
                ];
            case 'inventory_processing':
                return [
                    'product_ids' => [fake()->numberBetween(1, 50), fake()->numberBetween(1, 50)],
                    'quantities' => [fake()->numberBetween(1, 5), fake()->numberBetween(1, 5)],
                ];
            case 'payment_processing':
                return [
                    'payment_method' => fake()->randomElement(['card', 'bank', 'virtual_account', 'mobile']),
                    'amount' => fake()->randomFloat(2, 1000, 100000),
                    'payment_id' => fake()->uuid(),
                ];
            case 'notification_processing':
                return [
                    'email' => fake()->safeEmail(),
                    'phone' => fake()->phoneNumber(),
                    'notification_types' => ['email', 'sms'],
                ];
            case 'order_completed':
                return [
                    'completed_at' => now()->toIso8601String(),
                    'payment_id' => fake()->uuid(),
                ];
            case 'order_failed':
                return [
                    'failed_at' => now()->toIso8601String(),
                    'reason' => fake()->sentence(),
                    'step' => fake()->randomElement(['payment', 'inventory', 'notification']),
                ];
            default:
                return [];
        }
    }

    /**
     * 처리된 이벤트 상태 정의
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_processed' => true,
            'status' => 'success',
            'processed_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ]);
    }

    /**
     * 실패한 이벤트 상태 정의
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_processed' => true,
            'status' => 'failed',
            'error_message' => fake()->sentence(),
            'processed_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
        ]);
    }

    /**
     * 대기 중인 이벤트 상태 정의
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_processed' => false,
            'status' => 'pending',
            'processed_at' => null,
        ]);
    }
}
