<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_activity_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cook_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('environment');
            $table->string('token')->unique();
            $table->timestamps();
        });
    }
};
