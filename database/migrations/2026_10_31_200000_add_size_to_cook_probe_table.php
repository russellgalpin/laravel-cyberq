<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cook_probe', function (Blueprint $table) {
            $table->string('size')->nullable()->after('meat');
        });
    }
};
