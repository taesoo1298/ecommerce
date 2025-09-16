<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 특정 정보를 가진 테스트용 고객 생성
        Customer::factory()->create([
            'name' => '홍길동',
            'email' => 'customer@example.com',
            'phone' => '010-1234-5678',
            'address' => '서울시 강남구 테헤란로 123',
        ]);

        // 추가 테스트 고객 생성
        Customer::factory()->count(20)->create();
    }
}
