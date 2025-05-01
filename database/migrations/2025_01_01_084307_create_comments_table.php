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
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('inceptor_id');
            $table->string('inceptor_type');

            $table->foreignUuid('replied_to_id')->nullable()->constrained('comments')->onDelete('cascade');

            $table->foreignUuid('closest_parentComment_id')->nullable()->constrained('comments')->onDelete('cascade');

            // $table->foreignUuid('post_id')->constrained('posts')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
