<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlackoutDate extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'court_id',
        'date',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
