<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'type',
        'recipient',
        'subject',
        'content',
        'is_sent',
        'sent_at',
        'error',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_sent' => 'boolean',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the order that the notification belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope a query to only include sent notifications.
     */
    public function scopeSent($query)
    {
        return $query->where('is_sent', true);
    }

    /**
     * Scope a query to only include failed notifications.
     */
    public function scopeFailed($query)
    {
        return $query->where('is_sent', false)->whereNotNull('error');
    }

    /**
     * Scope a query to only include email notifications.
     */
    public function scopeEmail($query)
    {
        return $query->where('type', 'email');
    }

    /**
     * Scope a query to only include SMS notifications.
     */
    public function scopeSms($query)
    {
        return $query->where('type', 'sms');
    }
}
