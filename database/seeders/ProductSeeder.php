<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 카테고리별 주요 상품 예시 (상세 정보 포함)
        Product::factory()->create([
            'name' => '프리미엄 스마트폰',
            'description' => '최신 기술이 탑재된 프리미엄 스마트폰입니다.',
            'price' => 980000,
            'stock' => 50,
            'sku' => 'PHONE001',
        ]);

        Product::factory()->create([
            'name' => '고급 노트북',
            'description' => '업무와 게임 모두에 최적화된 고성능 노트북입니다.',
            'price' => 1500000,
            'stock' => 30,
            'sku' => 'LAPTOP001',
        ]);

        Product::factory()->create([
            'name' => '스마트 워치',
            'description' => '건강 관리와 알림 기능을 갖춘 스마트 워치입니다.',
            'price' => 250000,
            'stock' => 100,
            'sku' => 'WATCH001',
        ]);

        // 품절 상품 예시
        Product::factory()->outOfStock()->create([
            'name' => '한정판 이어폰',
            'description' => '한정 수량으로 제작된 프리미엄 이어폰입니다.',
            'price' => 350000,
            'sku' => 'EARPHONE001',
        ]);

        // 비활성화 상품 예시
        Product::factory()->inactive()->create([
            'name' => '구형 태블릿',
            'description' => '단종 예정인 이전 세대 태블릿입니다.',
            'price' => 200000,
            'stock' => 5,
            'sku' => 'TABLET001',
        ]);

        // 추가 랜덤 상품 생성
        Product::factory()->count(20)->create();
    }
}
