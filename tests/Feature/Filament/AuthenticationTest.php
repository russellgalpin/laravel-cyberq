<?php

use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Auth\Pages\Login;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Livewire\Livewire;

it('sends guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/cooks')->assertRedirect('/login');
    $this->get('/controller')->assertRedirect('/login');
});

it('offers a passkey on the login page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Authenticate using passkey');
});

it('still signs in with a password', function () {
    $user = User::factory()->create(['password' => 'correct-horse']);

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'correct-horse'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

it('lets users manage their passkeys from their profile', function () {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(PasskeyUser::class);

    $this->actingAs($user)
        ->get(EditProfile::getUrl())
        ->assertOk()
        ->assertSee('Passkeys');
});

it('hands out passkey registration options to a signed in user', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/user/passkeys/options')
        ->assertOk()
        ->assertJsonStructure(['options' => ['challenge', 'rp', 'user']]);
});

it('hands out passkey login options to guests', function () {
    $this->getJson('/passkeys/login/options')
        ->assertOk()
        ->assertJsonStructure(['options' => ['challenge']]);
});
