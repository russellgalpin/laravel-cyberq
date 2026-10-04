<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cooks', function (Blueprint $table) {
            $table->boolean('ended_automatically')->default(false)->after('ended_at');
        });
    }
};
