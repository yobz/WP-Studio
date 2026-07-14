<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('disconnected');
            $table->string('wordpress_version')->nullable();
            $table->string('theme')->nullable();
            $table->unsignedInteger('plugin_updates_available')->default(0);
            $table->unsignedInteger('storage_used_mb')->default(0);
            $table->unsignedInteger('storage_limit_mb')->default(10240);
            $table->timestamps();
            $table->softDeletes();

            $table->index('workspace_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
