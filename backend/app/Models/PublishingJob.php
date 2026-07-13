<?php

namespace App\Models;

use App\Enums\PublishingJobStatus;
use Database\Factories\PublishingJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Placeholder — records intent to publish, not an actually-processed
 * background job (no queue worker consumes these yet). See
 * docs/adr/0005-domain-model.md and `PublishingService`.
 */
class PublishingJob extends Model
{
    /** @use HasFactory<PublishingJobFactory> */
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

    /**
     * @param  Builder<PublishingJob>  $query
     * @return Builder<PublishingJob>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PublishingJobStatus::Pending);
    }
}
