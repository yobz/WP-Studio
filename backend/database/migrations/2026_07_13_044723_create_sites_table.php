<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Plain string, not a DB-level enum — SQLite has no native
            // enum type, and Laravel's enum casting on the Eloquent
            // model (see App\Models\Site) already gives us type safety
            // at the application layer without a driver-specific
            // column type.
            $table->string('status')->default('disconnected');
            $table->string('wordpress_version')->nullable();
            $table->string('theme')->nullable();
            $table->unsignedInteger('plugin_updates_available')->default(0);
            $table->unsignedInteger('storage_used_mb')->default(0);
            $table->unsignedInteger('storage_limit_mb')->default(10240);
            $table->timestamps();
            // A connected WordPress site is exactly the kind of record
            // an operator might remove by mistake (or want to
            // temporarily disconnect without losing its post history)
            // — recoverable delete, not permanent. See
            // docs/adr/0005-domain-model.md's "Soft deletes" section
            // for why this isn't applied to every table.
            $table->softDeletes();

            // SQLite, unlike MySQL/InnoDB, does not implicitly index a
            // foreign-key column just because `constrained()` added the
            // constraint — an explicit index is required here for
            // workspace-scoped lookups (`SiteController::index()`'s
            // `workspace_id` filter, and the future auth-scoped
            // dashboard queries Milestone 8 will add) to actually use
            // an index rather than a full table scan.
            $table->index('workspace_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
