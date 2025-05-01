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
        Schema::create('post_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tagged_id')->constrained('users')->onDelete('cascade');;
            $table->foreignUuid('post_id')->constrained('posts')->onDelete('cascade');;
            $table->unique(['tagged_id', 'post_id'], 'post_user_tag_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_users');
    }
};
