<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            //person to whom notification will go
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            //post/message/follow/story
            $table->uuid('first_parent_id')->onDelete('cascade');;

            $table->string('first_parent_type');
            //post-like ,post-comment

            //comment-like

            //message-like
            //story-like
            $table->uuid('second_parent_id')->onDelete('cascade')->nullable();
            $table->string('second_parent_type')->nullable();

            $table->index(['user_id', 'seen']);
            $table->index(['first_parent_id', 'first_parent_type']);
            $table->index(['second_parent_id', 'second_parent_type']);

            $table->boolean('seen')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
