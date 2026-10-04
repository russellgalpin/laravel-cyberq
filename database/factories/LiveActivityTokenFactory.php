<?php

namespace Database\Factories;

use App\Enums\ApnsEnvironment;
use App\Enums\LiveActivityTokenKind;
use App\Models\LiveActivityToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LiveActivityToken> */
class LiveActivityTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => LiveActivityTokenKind::Update,
            'environment' => ApnsEnvironment::Development,
            'token' => bin2hex(random_bytes(32)),
        ];
    }

    public function start(): static
    {
        return $this->state(['kind' => LiveActivityTokenKind::Start, 'cook_id' => null]);
    }
}
