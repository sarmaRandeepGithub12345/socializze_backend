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
        Schema::create('single_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('parent_id')->onDelete('cascade');;
            $table->string('parent_type');
            $table->integer('media_type')->default(0);
            // 0 - text m
            // 1 - photo m
            //   - video m
            // 2 - audio m
            // 3 - video_call
            // 4 - phone call
            // $table->foreignUuid('post_id')->constrained('posts')->onDelete('cascade');
            $table->string('aws_link');
            $table->string('thumbnail')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('single_files');
    }
};
