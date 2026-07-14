<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('mediable_type')->nullable();
            $table->unsignedBigInteger('mediable_id')->nullable();
            $table->string('collection')->nullable();

            $table->string('source');
            $table->string('source_id')->nullable();

            $table->string('disk');
            $table->string('storage_path');
            $table->string('original_url')->nullable();

            $table->string('filename');
            $table->string('extension', 16);
            $table->string('mime_type');
            $table->unsignedInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('hash', 64);
            $table->string('alt_text')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Not DB-level unique constraints: SoftDeletes makes a row
            // "logically gone but physically present," and a unique index
            // would block re-attaching a slot (e.g. a replaced featured
            // image) once the old row is soft-deleted. The one-per-slot and
            // no-duplicate-download invariants are enforced in the service/
            // mapper layer instead — the same tradeoff `posts` already
            // accepts for its own (site_id, wordpress_post_id) pairing.
            $table->index(['workspace_id', 'hash']);
            $table->index(['mediable_type', 'mediable_id', 'collection']);
            $table->index(['site_id', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
