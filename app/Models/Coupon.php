<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // 쿠폰 유효성 검사
    public function isValid($orderAmount = null): bool
    {
        // 활성화 상태 검사
        if (!$this->is_active) {
            return false;
        }

        // 사용 시작일 검사
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        // 만료일 검사
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // 최대 사용 횟수 검사
        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        // 최소 주문 금액 검사
        if ($orderAmount !== null && $this->min_order_amount > $orderAmount) {
            return false;
        }

        return true;
    }

    // 쿠폰 사용 횟수 증가
    public function incrementUsage(): bool
    {
        $this->used_count++;
        return $this->save();
    }

    // 쿠폰 할인 계산
    public function calculateDiscount($amount): float
    {
        if ($this->type === 'percentage') {
            return ($amount * $this->value) / 100;
        }

        return min($amount, $this->value);
    }
}
