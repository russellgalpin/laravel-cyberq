<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cook_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cook_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('probe')->default('');
            $table->integer('target')->nullable();
            $table->unsignedSmallInteger('missed_polls')->default(0);
            $table->timestamp('condition_since')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['cook_id', 'kind', 'probe']);
        });
    }
};
