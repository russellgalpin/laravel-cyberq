<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

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
        ]]);
});
