<?php

use App\Models\Cook;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

function cyberQXml(string $name): string
{
    return file_get_contents(__DIR__."/Fixtures/{$name}");
}

/** @param array<int, array{0: int, 1: ?int}> $readings temperature and set point keyed by minutes into the cook */
function recordReadings(Cook $cook, string $identifier, array $readings): void
{
    $probe = $cook->guru->probes->firstWhere('identifier', $identifier);

    foreach ($readings as $minutesIn => [$temperature, $setPoint]) {
        Reading::factory()->create([
            'cook_id' => $cook->id,
            'probe_id' => $probe->id,
            'temperature' => $temperature,
            'set_point' => $setPoint,
            'created_at' => $cook->started_at->copy()->addMinutes($minutesIn),
        ]);
    }
}
