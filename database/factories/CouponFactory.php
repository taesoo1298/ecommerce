<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['fixed', 'percentage']);
        $value = $type === 'fixed'
            ? fake()->randomFloat(2, 5, 100) // 고정 금액 쿠폰(5원~100원)
            : fake()->numberBetween(5, 30);  // 퍼센트 쿠폰(5%~30%)

        return [
            'code' => strtoupper(Str::random(8)),
            'type' => $type,
            'value' => $value,
            'min_order_amount' => fake()->randomFloat(2, 0, 50),
            'max_uses' => fake()->optional(0.7, null)->numberBetween(1, 100), // 70% 확률로 사용 제한 있음
            'used_count' => 0,
            'starts_at' => now(),
            'expires_at' => now()->addDays(fake()->numberBetween(10, 60)),
            'is_active' => fake()->boolean(90), // 90% 확률로 활성화 상태
        ];
    }

    /**
     * 고정 금액 쿠폰 생성
     */
    public function fixedAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'value' => fake()->randomFloat(2, 5, 100),
        ]);
    }

    /**
     * 퍼센트 할인 쿠폰 생성
     */
    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => fake()->numberBetween(5, 30),
        ]);
    }

    /**
     * 만료된 쿠폰 생성
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->subDays(1),
        ]);
    }
}
