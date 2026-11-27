<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cook_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cook_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('noted_at');
            $table->timestamps();

            $table->index(['cook_id', 'noted_at']);
        });
    }
};
