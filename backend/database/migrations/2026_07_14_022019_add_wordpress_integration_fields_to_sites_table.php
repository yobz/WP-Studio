<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('url')->nullable()->after('name');
            $table->string('php_version')->nullable()->after('theme');
            $table->unsignedInteger('plugin_count')->nullable()->after('plugin_updates_available');
            $table->unsignedInteger('user_count')->nullable()->after('plugin_count');
            $table->string('timezone')->nullable()->after('user_count');
            $table->string('language', 20)->nullable()->after('timezone');
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('connection_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'url',
                'php_version',
                'plugin_count',
                'user_count',
                'timezone',
                'language',
                'last_connected_at',
                'last_checked_at',
                'connection_error',
            ]);
        });
    }
};
