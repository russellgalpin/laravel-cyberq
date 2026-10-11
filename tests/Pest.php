<?php

use App\Models\Cook;
use App\Models\Probe;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

/**
 * Points the APNs client at a freshly generated P-256 key, as Apple's .p8 keys are.
 */
function fakeApnsKey(): void
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);

    $path = tempnam(sys_get_temp_dir(), 'apns');
    file_put_contents($path, $pem);

    config([
        'services.apns.key_id' => 'ABC123DEFG',
        'services.apns.team_id' => 'V2H9538867',
        'services.apns.private_key_path' => $path,
        'services.apns.bundle_id' => 'net.lrhosting.cyberq',
    ]);
}

/**
 * Records a food probe (and the pit) cooking the way real joints do: a quick
 * rise, a stall from 150-170°F, then a slower finish. A speed of 2 is a
 * joint that cooks twice as fast. Readings are one a minute until the food
 * reaches $until or $minutes pass.
 *
 * @return int minutes the food took to reach $until, or $minutes if it did not
 */
function simulateCook(Cook $cook, string $identifier, float $speed = 1.0, float $pit = 250, float $from = 40, float $until = 203, int $minutes = 2000, bool $recordPit = true): int
{
    $food = $from;
    $rows = [];
    $probes = $cook->guru->probes->pluck('id', 'identifier');

    for ($minute = 0; $minute <= $minutes; $minute++) {
        $at = $cook->started_at->copy()->addMinutes($minute)->toDateTimeString();
        $rows[] = ['cook_id' => $cook->id, 'probe_id' => $probes[$identifier], 'temperature' => (int) round($food * 10), 'set_point' => 2030, 'created_at' => $at, 'updated_at' => $at];

        if ($recordPit) {
            $rows[] = ['cook_id' => $cook->id, 'probe_id' => $probes[Probe::PIT], 'temperature' => (int) round($pit * 10), 'set_point' => (int) round($pit * 10), 'created_at' => $at, 'updated_at' => $at];
        }

        if ($food >= $until) {
            break;
        }

        $food += simulatedRisePerHour($food, $pit) * $speed / 60;
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('readings')->insert($chunk);
    }

    return $minute;
}

function simulatedRisePerHour(float $food, float $pit): float
{
    $band = match (true) {
        $food < 150 => 4.0,
        $food < 170 => 0.8,
        default => 2.0,
    };

    return $band * sqrt(max($pit - $food, 15));
}
