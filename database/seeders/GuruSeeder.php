<?php

namespace Database\Seeders;

use App\Models\Cook;
use App\Models\Guru;
use App\Models\Probe;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class GuruSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $guru = Guru::create([
            'name' => 'Home',
            'ip' => env('HOME_GURU_IP'),
            'username' => env('HOME_GURU_USERNAME'),
            'password' => env('HOME_GURU_PASSWORD')
        ]);

        Probe::create([
            'guru_id' => $guru->id,
            'name' => 'Pit',
            'identifier' => 'COOK_TEMP'
        ]);

        Probe::create([
            'guru_id' => $guru->id,
            'name' => 'Probe 1',
            'identifier' => 'FOOD1_TEMP'
        ]);

        Cook::create([
            'guru_id' => $guru->id,
            'name' => 'Pulled Port',
            'started_at' => Carbon::now(),
        ]);


    }
}
