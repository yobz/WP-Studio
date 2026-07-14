<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_id',
        'title',
        'status',
        'published_at',
        'wordpress_post_id',
        'wordpress_modified_at',
        'wordpress_url',
        'sync_status',
        'sync_hash',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'wordpress_post_id' => 'integer',
            'wordpress_modified_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function publishingJobs(): HasMany
    {
        return $this->hasMany(PublishingJob::class);
    }

    public function featuredImage(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable')->where('collection', 'featured_image');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published);
    }

    public function scopeUnpublished(Builder $query): Builder
    {
        return $query->whereIn('status', [PostStatus::Draft, PostStatus::InReview]);
    }

    public function scopeSyncedFromWordPress(Builder $query): Builder
    {
        return $query->whereNotNull('wordpress_post_id');
    }
}
