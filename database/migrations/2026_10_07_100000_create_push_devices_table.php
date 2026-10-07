<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('environment');
            $table->boolean('pit_alerts')->default(true);
            $table->boolean('food_alerts')->default(true);
            $table->boolean('offline_alerts')->default(true);
            $table->timestamps();
        });
    }
};
