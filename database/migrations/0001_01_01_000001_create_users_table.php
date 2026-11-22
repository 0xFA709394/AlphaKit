<?php

declare(strict_types=1);

use AlphaKit\Database\Migration;
use AlphaKit\Database\Schema\Blueprint;
use AlphaKit\Database\Schema\Schema;

final class CreateUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(1);
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}
