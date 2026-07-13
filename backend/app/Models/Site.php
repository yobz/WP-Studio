<?php

namespace App\Models;

use App\Enums\SiteStatus;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory, SoftDeletes;

    // Explicit allow-list, not `$guarded = []` — mass-assignment
    // protection per docs/adr/0004-backend-foundation.md's security
    // foundation section.
    protected $fillable = [
        'workspace_id',
        'name',
        'status',
        'wordpress_version',
        'theme',
        'plugin_updates_available',
        'storage_used_mb',
        'storage_limit_mb',
    ];

    protected function casts(): array
    {
        return [
            'status' => SiteStatus::class,
            'plugin_updates_available' => 'integer',
            'storage_used_mb' => 'integer',
            'storage_limit_mb' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function analyticsSnapshots(): HasMany
    {
        return $this->hasMany(AnalyticsSnapshot::class);
    }

    /**
     * @param  Builder<Site>  $query
     * @return Builder<Site>
     */
    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', SiteStatus::Connected);
    }
}
