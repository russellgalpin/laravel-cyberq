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
