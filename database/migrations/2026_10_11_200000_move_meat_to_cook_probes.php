<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A cook can have different things on each food probe, so the meat (and an
 * optional name, like "Big shoulder") belongs to the cook's probe rather
 * than the cook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cook_probe', function (Blueprint $table) {
            $table->string('meat')->nullable();
            $table->string('name')->nullable();
        });

        DB::table('cooks')->whereNotNull('meat')->get(['id', 'meat'])->each(function (object $cook) {
            DB::table('cook_probe')
                ->where('cook_id', $cook->id)
                ->whereIn('probe_id', DB::table('probes')->whereIn('identifier', ['FOOD1_TEMP', 'FOOD2_TEMP', 'FOOD3_TEMP'])->select('id'))
                ->update(['meat' => $cook->meat]);
        });

        Schema::table('cooks', function (Blueprint $table) {
            $table->dropColumn('meat');
        });
    }
};
