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
        Schema::create('likes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            //1L - 1U
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');;
            $table->foreignUuid('likeable_id');
            $table->string('likeable_type');
            //one person will be able to like something only once
            $table->unique(['user_id', 'likeable_id'], 'user_like_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
