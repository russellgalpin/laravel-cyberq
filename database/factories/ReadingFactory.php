<?php

namespace Database\Factories;

use App\Models\Cook;
use App\Models\Probe;
use App\Models\Reading;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reading> */
class ReadingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cook_id' => Cook::factory(),
            'probe_id' => Probe::factory(),
            'temperature' => 2250,
            'set_point' => 2250,
        ];
    }
}
