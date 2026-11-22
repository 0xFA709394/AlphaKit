<?php

declare(strict_types=1);

use AlphaKit\Database\Migration;
use AlphaKit\Database\Schema\Blueprint;
use AlphaKit\Database\Schema\Schema;

final class CreatePostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content')->nullable();
            $table->boolean('published')->default(0);
            $table->integer('views')->default(0);
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
}
