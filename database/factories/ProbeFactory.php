<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\Probe;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Probe> */
class ProbeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'guru_id' => Guru::factory(),
            'name' => 'Pit',
            'identifier' => Probe::PIT,
        ];
    }
}
