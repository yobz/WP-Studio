<?php

namespace App\Models;

use App\Enums\PublishingJobStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishingJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'status',
        'attempted_at',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => PublishingJobStatus::class,
            'attempted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PublishingJobStatus::Pending);
    }
}
