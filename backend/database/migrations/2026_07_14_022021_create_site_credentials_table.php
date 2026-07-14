<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('wp_username');
            $table->text('application_password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_credentials');
    }
};
