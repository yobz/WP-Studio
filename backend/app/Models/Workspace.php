<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The tenant boundary. Every `Site` (and transitively, every `Post`,
 * `AnalyticsSnapshot`, `PublishingJob`) belongs to exactly one
 * Workspace; a `User` can belong to more than one (see
 * `workspace_user` and `users()` below). No route currently scopes
 * queries by workspace — Milestone 8 (Authentication) is what gives a
 * request a "current user," which is what a "current workspace" would
 * need to be resolved from. See docs/adr/0005-domain-model.md.
 */
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', WorkspaceRole::Owner->value);
    }

    /**
     * Used by policies (see App\Policies) — deliberately a plain
     * membership-lookup query rather than caching `users` on the
     * instance, since a policy check should always reflect the
     * current pivot state, not a possibly-stale eager-loaded
     * collection.
     */
    public function roleFor(User $user): ?WorkspaceRole
    {
        $role = $this->users()
            ->where('users.id', $user->id)
            ->first()
            ?->pivot
            ?->role;

        return $role ? WorkspaceRole::from($role) : null;
    }

    public function hasMember(User $user): bool
    {
        return $this->roleFor($user) !== null;
    }
}
