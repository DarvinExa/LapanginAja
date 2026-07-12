<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'booking_id',
        'channel',
        'type',
        'status',
        'recipient',
        'content',
        'error_message',
        'retry_count',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'type' => NotificationType::class,
            'status' => NotificationStatus::class,
            'created_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
