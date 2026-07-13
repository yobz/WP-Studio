<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Many-to-many, not a `workspace_id` column on `users` — a real
     * SaaS user commonly belongs to more than one tenant (an agency
     * managing several clients' workspaces, a freelancer's own site
     * plus a client's). A single-workspace-per-user column would be
     * the wrong default to build Milestone 8's auth against.
     */
    public function up(): void
    {
        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Plain string, same reasoning as Site/Post status columns
            // — see App\Enums\WorkspaceRole for the application-layer
            // enum this casts to.
            $table->string('role')->default('member');
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            // The unique index above covers `workspace_id`-first
            // lookups (leftmost-prefix), but not `user_id` alone —
            // `$user->workspaces` (User::workspaces(), "which
            // workspaces does this user belong to") filters by
            // `user_id` only, which needs its own index to avoid a
            // full table scan on SQLite (see the sites migration for
            // the same class of gap).
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_user');
    }
};
