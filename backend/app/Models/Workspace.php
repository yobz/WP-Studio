<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function aiJobs(): HasMany
    {
        return $this->hasMany(AiJob::class);
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
