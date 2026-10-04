<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('gurus')->pluck('id')->each(function (int $guruId) {
            $alreadyRecorded = DB::table('probes')
                ->where('guru_id', $guruId)
                ->where('identifier', 'FOOD3_TEMP')
                ->exists();

            if ($alreadyRecorded) {
                return;
            }

            DB::table('probes')->insert([
                'guru_id' => $guruId,
                'name' => 'Probe 3',
                'identifier' => 'FOOD3_TEMP',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
};
