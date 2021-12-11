<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReadingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gurus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->ipAddress('ip');
            $table->string('username');
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('cooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('probes', function (Blueprint  $table) {
           $table->id();
           $table->foreignId('guru_id')->constrained();
           $table->string('name');
           $table->string('identifier');
            $table->timestamps();
        });

        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cook_id')->constrained();
            $table->foreignId('probe_id')->constrained();
            $table->integer('temperature');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('readings');
        Schema::dropIfExists('probes');
        Schema::dropIfExists('cooks');
        Schema::dropIfExists('gurus');
    }
}
