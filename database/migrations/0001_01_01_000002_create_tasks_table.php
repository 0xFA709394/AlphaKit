<?php

declare(strict_types=1);

use AlphaKit\Database\Migration;
use AlphaKit\Database\Schema\Blueprint;
use AlphaKit\Database\Schema\Schema;

final class CreateTasksTable extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('done')->default(0);
            $table->string('tags')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
}
