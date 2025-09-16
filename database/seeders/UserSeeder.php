<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 테스트용 기본 사용자 생성
        User::factory()->create([
            'name' => '테스트 관리자',
            'email' => 'admin@example.com',
        ]);

        // 추가 테스트 사용자 생성
        User::factory()->count(5)->create();
    }
}
