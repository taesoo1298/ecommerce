<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'event_type',
        'payload',
        'is_processed',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // 이벤트 처리 상태 설정
    public function markProcessed(string $status = 'success'): bool
    {
        $this->is_processed = true;
        $this->status = $status;
        $this->processed_at = now();
        return $this->save();
    }

    // 이벤트 처리 실패 설정
    public function markFailed(string $errorMessage): bool
    {
        $this->is_processed = true;
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->processed_at = now();
        return $this->save();
    }
}
