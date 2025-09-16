<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['email', 'sms']);
        $isSent = fake()->boolean(80); // 80% 확률로 전송 성공

        return [
            'order_id' => Order::factory(),
            'type' => $type,
            'recipient' => $type === 'email' ? fake()->safeEmail() : fake()->phoneNumber(),
            'subject' => $type === 'email' ? fake()->sentence() : null,
            'content' => fake()->paragraph(),
            'is_sent' => $isSent,
            'sent_at' => $isSent ? now()->subMinutes(fake()->numberBetween(1, 60)) : null,
            'error' => $isSent ? null : fake()->sentence(),
        ];
    }

    /**
     * 이메일 알림 상태 정의
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'email',
            'recipient' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
        ]);
    }

    /**
     * SMS 알림 상태 정의
     */
    public function sms(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sms',
            'recipient' => fake()->phoneNumber(),
            'subject' => null,
        ]);
    }

    /**
     * 성공한 알림 상태 정의
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sent' => true,
            'sent_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
            'error' => null,
        ]);
    }

    /**
     * 실패한 알림 상태 정의
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sent' => false,
            'sent_at' => null,
            'error' => fake()->sentence(),
        ]);
    }
}
