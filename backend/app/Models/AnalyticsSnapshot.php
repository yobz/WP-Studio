<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'snapshot_date',
        'visitors',
        'posts_published',
        'storage_used_mb',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'visitors' => 'integer',
            'posts_published' => 'integer',
            'storage_used_mb' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeMostRecentFirst(Builder $query): Builder
    {
        return $query->orderByDesc('snapshot_date');
    }
}
