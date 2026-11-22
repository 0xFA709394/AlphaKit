<?php

declare(strict_types=1);

use AlphaKit\Database\Migration;
use AlphaKit\Database\Schema\Blueprint;
use AlphaKit\Database\Schema\Schema;

final class CreateCommentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('post_id');
            $table->string('author_name');
            $table->text('body');
            $table->timestamps();
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
}
