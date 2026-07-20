<?php

namespace App\Models;

use App\Enums\SiteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'name',
        'url',
        'status',
        'wordpress_version',
        'theme',
        'php_version',
        'plugin_updates_available',
        'plugin_count',
        'user_count',
        'timezone',
        'language',
        'storage_used_mb',
        'storage_limit_mb',
        'last_connected_at',
        'last_checked_at',
        'last_synced_at',
        'connection_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => SiteStatus::class,
            'plugin_updates_available' => 'integer',
            'plugin_count' => 'integer',
            'user_count' => 'integer',
            'storage_used_mb' => 'integer',
            'storage_limit_mb' => 'integer',
            'last_connected_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_synced_at' => 'datetime',
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

    public function credential(): HasOne
    {
        return $this->hasOne(SiteCredential::class);
    }

    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', SiteStatus::Connected);
    }
}
