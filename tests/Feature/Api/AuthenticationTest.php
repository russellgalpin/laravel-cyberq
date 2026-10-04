<?php

use App\Http\Controllers\Api\PasskeyChallengesController;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use Laravel\Sanctum\Sanctum;

function fakeAssertion(): array
{
    $base64Url = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    return [
        'id' => $base64Url('credential-id'),
        'rawId' => $base64Url('credential-id'),
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => $base64Url(json_encode(['type' => 'webauthn.get', 'challenge' => $base64Url('challenge'), 'origin' => 'https://cyberq.test'])),
            'authenticatorData' => $base64Url(hash('sha256', 'cyberq.test', true)."\x05\x00\x00\x00\x01"),
            'signature' => $base64Url('signature'),
            'userHandle' => null,
        ],
    ];
}

it('issues a token for a correct email and password', function () {
    $user = User::factory()->create(['password' => 'correct-horse']);

    $response = $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'correct-horse',
        'device_name' => "Russ's iPhone",
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonStructure(['token']);

    expect($user->tokens()->first()->name)->toBe("Russ's iPhone");

    $this->withToken($response->json('token'))->getJson('/api/v1/user')->assertOk()->assertJsonPath('data.id', $user->id);
});

it('refuses a wrong password', function () {
    $user = User::factory()->create(['password' => 'correct-horse']);

    $this->postJson('/api/v1/tokens', ['email' => $user->email, 'password' => 'nope', 'device_name' => 'iPhone'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('slows down repeated sign-in attempts', function () {
    foreach (range(1, 6) as $attempt) {
        $this->postJson('/api/v1/tokens', ['email' => 'a@example.com', 'password' => 'x', 'device_name' => 'iPhone']);
    }

    $this->postJson('/api/v1/tokens', ['email' => 'a@example.com', 'password' => 'x', 'device_name' => 'iPhone'])
        ->assertTooManyRequests();
});

it('keeps everything else behind a token', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/user'],
    ['GET', '/api/v1/dashboard'],
    ['GET', '/api/v1/cooks'],
    ['POST', '/api/v1/cooks'],
    ['GET', '/api/v1/controller-settings'],
    ['PATCH', '/api/v1/controller-settings'],
]);

it('signs out by revoking the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/tokens/current')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('starts a passkey sign-in with a challenge kept for the app', function () {
    $response = $this->postJson('/api/v1/passkey-challenges')->assertCreated();

    expect(Cache::has(PasskeyChallengesController::cacheKey($response->json('challenge_id'))))->toBeTrue()
        ->and($response->json('options.challenge'))->toBeString()
        ->and($response->json('options.rpId'))->toBe(parse_url(config('app.url'), PHP_URL_HOST));
});

it('issues a token once a passkey is verified, and only once per challenge', function () {
    $user = User::factory()->create();
    $passkey = new Passkey;
    $passkey->setRelation('user', $user);

    $this->mock(VerifyPasskey::class)->shouldReceive('__invoke')->once()->andReturn($passkey);

    $challengeId = $this->postJson('/api/v1/passkey-challenges')->json('challenge_id');
    $payload = ['challenge_id' => $challengeId, 'device_name' => 'iPhone', 'credential' => fakeAssertion()];

    $this->postJson('/api/v1/passkey-tokens', $payload)
        ->assertCreated()
        ->assertJsonPath('user.id', $user->id);

    $this->postJson('/api/v1/passkey-tokens', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('challenge_id');
});

it('refuses a passkey that does not verify', function () {
    $this->mock(VerifyPasskey::class)->shouldReceive('__invoke')->andThrow(InvalidPasskeyException::make('Passkey not recognized.'));

    $challengeId = $this->postJson('/api/v1/passkey-challenges')->json('challenge_id');

    $this->postJson('/api/v1/passkey-tokens', ['challenge_id' => $challengeId, 'device_name' => 'iPhone', 'credential' => fakeAssertion()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credential' => 'Passkey not recognized.']);
});

it('refuses a passkey answer to an unknown challenge', function () {
    $this->postJson('/api/v1/passkey-tokens', ['challenge_id' => (string) Str::uuid(), 'device_name' => 'iPhone', 'credential' => fakeAssertion()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('challenge_id');
});

it('tells iOS which app may use the passkeys', function () {
    config(['services.ios.app_ids' => ['ABCDE12345.net.lrhosting.cyberq']]);

    $this->get('/.well-known/apple-app-site-association')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['webcredentials' => ['apps' => ['ABCDE12345.net.lrhosting.cyberq']]]);
});

it('reuses an existing token in the tests helper', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/user')->assertOk();
});

it('identifies itself to the app without signing in', function () {
    $this->getJson('/api/v1/instance')
        ->assertOk()
        ->assertExactJson(['data' => [
            'app' => 'cyberq',
            'name' => config('app.name'),
            'api_version' => 1,
            'passkey_relying_party' => parse_url(config('app.url'), PHP_URL_HOST),
        ]]);
});
