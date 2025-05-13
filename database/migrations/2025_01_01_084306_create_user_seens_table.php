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
        Schema::create('user_seens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            // $table->foreignUuid('message_id')->constrained('messages')->onDelete('cascade');
            $table->foreignUuid('parentSeen_id')->onDelete('cascade'); //message ,story
            $table->string('parentSeen_type');
            //one person will be able to see something only once
            $table->unique(['user_id', 'parentSeen_id'], 'user_parentseen_unique');

            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_seens');
    }
};
