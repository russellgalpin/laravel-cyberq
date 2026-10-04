<?php

namespace Database\Factories;

use App\Models\Guru;
use App\Models\Probe;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Guru> */
class GuruFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Home',
            'ip' => 'cyberq.test',
            'username' => 'admin',
            'password' => 'secret',
        ];
    }

    public function withProbes(): static
    {
        return $this->afterCreating(function (Guru $guru) {
            collect([
                Probe::PIT => 'Pit',
                Probe::FOOD1 => 'Probe 1',
                Probe::FOOD2 => 'Probe 2',
                Probe::FOOD3 => 'Probe 3',
                Probe::FAN_OUTPUT => 'Output',
                'COOK_RAMP' => 'Ramp',
            ])->each(fn (string $name, string $identifier) => $guru->probes()->create([
                'name' => $name,
                'identifier' => $identifier,
            ]));
        });
    }
}
