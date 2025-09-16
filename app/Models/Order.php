<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'order_number',
        'subtotal',
        'tax',
        'discount',
        'total',
        'status',
        'notes',
        'coupon_processed_at',
        'inventory_processed_at',
        'payment_processed_at',
        'notification_sent_at',
        'is_completed',
        'failure_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'is_completed' => 'boolean',
        'coupon_processed_at' => 'datetime',
        'inventory_processed_at' => 'datetime',
        'payment_processed_at' => 'datetime',
        'notification_sent_at' => 'datetime',
    ];

    // 관계 설정
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class);
    }

    // 상태 확인 메서드들
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->is_completed;
    }

    public function isCouponProcessed(): bool
    {
        return $this->coupon_processed_at !== null;
    }

    public function isInventoryProcessed(): bool
    {
        return $this->inventory_processed_at !== null;
    }

    public function isPaymentProcessed(): bool
    {
        return $this->payment_processed_at !== null;
    }

    public function isNotificationSent(): bool
    {
        return $this->notification_sent_at !== null;
    }

    // 상태 업데이트 메서드들
    public function markCouponProcessed(): bool
    {
        $this->coupon_processed_at = now();
        return $this->save();
    }

    public function markInventoryProcessed(): bool
    {
        $this->inventory_processed_at = now();
        return $this->save();
    }

    public function markPaymentProcessed(): bool
    {
        $this->payment_processed_at = now();
        return $this->save();
    }

    public function markNotificationSent(): bool
    {
        $this->notification_sent_at = now();
        return $this->save();
    }

    public function complete(): bool
    {
        $this->status = 'completed';
        $this->is_completed = true;
        return $this->save();
    }

    public function fail(string $reason): bool
    {
        $this->status = 'failed';
        $this->failure_reason = $reason;
        return $this->save();
    }

    // 유틸리티 메서드
    public function getTotalItems(): int
    {
        return $this->items->sum('quantity');
    }

    public function recalculateTotal(): bool
    {
        $this->subtotal = $this->items->sum('total');
        $this->total = $this->subtotal + $this->tax - $this->discount;
        return $this->save();
    }
}
