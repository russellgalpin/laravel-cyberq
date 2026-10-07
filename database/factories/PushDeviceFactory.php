<?php

namespace Database\Factories;

use App\Enums\ApnsEnvironment;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PushDevice> */
class PushDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => bin2hex(random_bytes(32)),
            'environment' => ApnsEnvironment::Development,
        ];
    }
}
