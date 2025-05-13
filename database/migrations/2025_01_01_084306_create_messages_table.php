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
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sender_id')->constrained('users')->onDelete('cascade');//one:user to many:messages
            $table->foreignUuid('chat_id')->constrained('chats')->onDelete('cascade');//one:chat to many:messages
            $table->text('message')->nullable();            
            $table->boolean('is_missed_call')->default(false);
            $table->integer('media_type')->default(0);
            // 0 - text m
            // 1 - photo m
            //   - video m
            // 2 - audio m
            // 3 - video_call
            // 4 - phone call
            // $table->foreignUuid('post_id')->constrained('posts')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
