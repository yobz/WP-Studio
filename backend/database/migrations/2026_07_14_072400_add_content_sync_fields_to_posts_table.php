<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedBigInteger('wordpress_post_id')->nullable()->after('site_id');
            $table->timestamp('wordpress_modified_at')->nullable();
            $table->string('wordpress_url')->nullable();
            $table->string('sync_status')->nullable();
            $table->string('sync_hash')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->unique(['site_id', 'wordpress_post_id']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'wordpress_post_id']);
            $table->dropColumn([
                'wordpress_post_id',
                'wordpress_modified_at',
                'wordpress_url',
                'sync_status',
                'sync_hash',
                'last_synced_at',
            ]);
        });
    }
};
