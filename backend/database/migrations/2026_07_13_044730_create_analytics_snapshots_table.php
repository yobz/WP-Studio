<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per site per day — a periodic snapshot, not a raw event
     * stream. A real analytics *events* schema (page views, sessions,
     * referrers) is explicitly future scope (a dedicated Analytics
     * milestone); this table's job is narrower: give the Dashboard a
     * real historical source to compute trends from (today vs. a prior
     * snapshot), replacing the single mutable `sites.monthly_visitors`
     * column Milestone 6 used as a placeholder. See
     * docs/adr/0005-domain-model.md.
     */
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('posts_published')->default(0);
            $table->unsignedInteger('storage_used_mb')->default(0);
            $table->timestamps();

            // One snapshot per site per day — re-running whatever
            // future job produces these should upsert, not duplicate.
            $table->unique(['site_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
