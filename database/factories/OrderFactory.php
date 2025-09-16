<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 1000, 100000);
        $tax = $subtotal * 0.1; // 10% 세금
        $discount = fake()->boolean(30) ? fake()->randomFloat(2, 500, 5000) : 0; // 30% 확률로 할인 적용
        $total = $subtotal + $tax - $discount;

        $statusOptions = ['pending', 'processing', 'completed', 'failed', 'cancelled'];
        $status = fake()->randomElement($statusOptions);

        $isCompleted = $status === 'completed';

        // 현재로부터 최대 30일 전 날짜 생성
        $createdAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'customer_id' => Customer::factory(),
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'status' => $status,
            'notes' => fake()->optional(0.3)->paragraph(), // 30% 확률로 주문 메모 추가
            'is_completed' => $isCompleted,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * 진행 중인 주문 상태 정의
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'is_completed' => false,
        ]);
    }

    /**
     * 처리 중인 주문 상태 정의
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'is_completed' => false,
            'coupon_processed_at' => now(),
            'inventory_processed_at' => now(),
        ]);
    }

    /**
     * 완료된 주문 상태 정의
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'is_completed' => true,
            'coupon_processed_at' => now()->subMinutes(fake()->numberBetween(30, 60)),
            'inventory_processed_at' => now()->subMinutes(fake()->numberBetween(20, 50)),
            'payment_processed_at' => now()->subMinutes(fake()->numberBetween(10, 40)),
            'notification_sent_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
        ]);
    }

    /**
     * 실패한 주문 상태 정의
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'is_completed' => false,
            'failure_reason' => fake()->sentence(),
        ]);
    }

    /**
     * 취소된 주문 상태 정의
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'is_completed' => false,
        ]);
    }
}
