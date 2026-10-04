<?php

namespace Database\Factories;

use App\Models\Cook;
use App\Models\Guru;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cook> */
class CookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'guru_id' => Guru::factory()->withProbes(),
            'name' => fake()->randomElement(['Pulled pork', 'Brisket', 'Lamb shoulder', 'Ribs']),
            'started_at' => now()->subHours(3),
            'ended_at' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state([
            'started_at' => now()->subDays(3),
            'ended_at' => now()->subDays(2),
        ]);
    }
}
