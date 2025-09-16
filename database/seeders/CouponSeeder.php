<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 고정 금액 할인 쿠폰
        Coupon::factory()->fixedAmount()->create([
            'code' => 'WELCOME5000',
            'value' => 5000,
            'min_order_amount' => 50000,
            'max_uses' => 1000,
            'starts_at' => now(),
            'expires_at' => now()->addMonths(3),
            'is_active' => true,
        ]);

        // 퍼센트 할인 쿠폰
        Coupon::factory()->percentage()->create([
            'code' => 'SAVE10PCT',
            'value' => 10, // 10% 할인
            'min_order_amount' => 30000,
            'max_uses' => 500,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);

        // 만료된 쿠폰
        Coupon::factory()->expired()->create([
            'code' => 'EXPIRED20',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
        ]);

        // 비활성화된 쿠폰
        Coupon::factory()->create([
            'code' => 'DISABLED15',
            'type' => 'percentage',
            'value' => 15,
            'is_active' => false,
        ]);

        // 추가 랜덤 쿠폰 생성
        Coupon::factory()->count(10)->create();
    }
}
