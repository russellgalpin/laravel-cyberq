<?php

use App\Enums\ApnsEnvironment;
use App\Services\Apns\ApnsClient;
use App\Services\Apns\ApnsResult;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    fakeApnsKey();
});

it('sends a Live Activity push to the right environment with a signed token', function () {
    Http::fake(['api.sandbox.push.apple.com/*' => Http::response()]);

    $result = app(ApnsClient::class)->sendLiveActivity('abc123', ApnsEnvironment::Development, ['aps' => ['event' => 'update']]);

    expect($result)->toBe(ApnsResult::Sent);

    Http::assertSent(function (Request $request) {
        [$header, $claims, $signature] = explode('.', str($request->header('Authorization')[0])->after('bearer ')->toString());

        return $request->url() === 'https://api.sandbox.push.apple.com/3/device/abc123'
            && $request->header('apns-push-type')[0] === 'liveactivity'
            && $request->header('apns-topic')[0] === 'net.lrhosting.cyberq.push-type.liveactivity'
            && $request->header('apns-priority')[0] === '10'
            && json_decode(base64_decode(strtr($header, '-_', '+/')), true) === ['alg' => 'ES256', 'kid' => 'ABC123DEFG']
            && json_decode(base64_decode(strtr($claims, '-_', '+/')), true)['iss'] === 'V2H9538867'
            && strlen(base64_decode(strtr($signature, '-_', '+/'))) === 64
            && $request['aps']['event'] === 'update';
    });
});

it('produces signatures Apple can verify', function () {
    $key = openssl_pkey_get_private(file_get_contents(config('services.apns.private_key_path')));
    openssl_sign('payload', $der, $key, OPENSSL_ALGO_SHA256);

    $jose = ApnsClient::derToJose($der);
    $back = fn (string $integer) => "\x02".chr(strlen($integer)).$integer;
    $trim = fn (string $integer) => (ord(ltrim($integer, "\x00")[0]) & 0x80) ? "\x00".ltrim($integer, "\x00") : ltrim($integer, "\x00");
    $sequence = $back($trim(substr($jose, 0, 32))).$back($trim(substr($jose, 32)));
    $rebuilt = "\x30".chr(strlen($sequence)).$sequence;

    expect(strlen($jose))->toBe(64)
        ->and(openssl_verify('payload', $rebuilt, openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256))->toBe(1);
});

it('reports tokens Apple no longer accepts', function (int $status, string $reason) {
    Http::fake(['*' => Http::response(['reason' => $reason], $status)]);

    expect(app(ApnsClient::class)->sendLiveActivity('abc123', ApnsEnvironment::Production, []))->toBe(ApnsResult::TokenInvalid);
})->with([
    [410, 'Unregistered'],
    [400, 'BadDeviceToken'],
]);

it('reports other failures without dropping the token', function () {
    Http::fake(['*' => Http::response(['reason' => 'InternalServerError'], 500)]);

    expect(app(ApnsClient::class)->sendLiveActivity('abc123', ApnsEnvironment::Production, []))->toBe(ApnsResult::Failed);
});

it('is switched off until a key is configured', function () {
    config(['services.apns.private_key_path' => '/nonexistent.p8']);

    expect(app(ApnsClient::class)->isConfigured())->toBeFalse();
});
