<?php

use App\Models\Cook;
use App\Models\LiveActivityToken;
use App\Models\Probe;
use App\Models\User;
use App\Support\CookSnapshot;
use App\Support\LiveActivities\LiveActivityBroadcaster;
use App\Support\LiveActivities\LiveActivityContent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    fakeApnsKey();
    $this->freezeSecond();
    $this->apnsResponse = Http::response();

    Http::fake(fn (Request $request) => str_contains($request->url(), 'push.apple.com') ?
        $this->apnsResponse :
        Http::response(cyberQXml('all.xml')));
});

function pushesTo(string $token): Collection
{
    return collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (Request $request) => str_ends_with($request->url(), "/3/device/{$token}"))
        ->values();
}

it('updates the Live Activity after each reading', function () {
    $cook = Cook::factory()->create();
    $token = LiveActivityToken::factory()->create(['cook_id' => $cook->id]);
    $otherCooksToken = LiveActivityToken::factory()->create(['cook_id' => Cook::factory()->ended()->create()->id]);

    $this->artisan('cooks:run')->assertSuccessful();

    $push = pushesTo($token->token)->sole();
    $state = $push['aps']['content-state'];

    expect($push['aps']['event'])->toBe('update')
        ->and($push['aps']['stale-date'])->toBe(now()->addMinutes(5)->getTimestamp())
        ->and($state['pit'])->toBe(265.7)
        ->and($state['pitTarget'])->toEqual(240)
        ->and($state['foods'][0])->toMatchArray(['label' => 'Food 1', 'temperature' => 170.6, 'done' => false])
        ->and($state['ended'])->toBeFalse()
        ->and(pushesTo($otherCooksToken->token))->toBeEmpty();
});

it('starts a Live Activity on phones when a cook begins', function () {
    $startToken = LiveActivityToken::factory()->start()->create();

    $cook = Cook::factory()->create(['name' => 'Brisket']);

    $push = pushesTo($startToken->token)->sole();

    expect($push['aps']['event'])->toBe('start')
        ->and($push['aps']['attributes-type'])->toBe('CookActivityAttributes')
        ->and($push['aps']['attributes'])->toMatchArray(['cookId' => $cook->id, 'cookName' => 'Brisket'])
        ->and($push['aps']['alert'])->toBe(['title' => 'Cook started', 'body' => 'Brisket']);
});

it('does not start a second Live Activity on a phone already showing the cook', function () {
    $user = User::factory()->create();
    $startToken = LiveActivityToken::factory()->start()->create(['user_id' => $user->id]);
    $cook = Cook::factory()->create(['started_at' => now()->addHour()]);
    LiveActivityToken::factory()->create(['user_id' => $user->id, 'cook_id' => $cook->id]);

    app(LiveActivityBroadcaster::class)->start($cook);

    expect(pushesTo($startToken->token))->toBeEmpty();
});

it('does not start Live Activities for cooks that have not begun', function () {
    $startToken = LiveActivityToken::factory()->start()->create();

    Cook::factory()->create(['started_at' => now()->addDay()]);

    expect(pushesTo($startToken->token))->toBeEmpty();
});

it('ends the Live Activity with the cook and forgets its tokens', function (Closure $end) {
    $cook = Cook::factory()->create(['started_at' => now()->subDay()]);
    $token = LiveActivityToken::factory()->create(['cook_id' => $cook->id]);

    $end($cook);

    $push = pushesTo($token->token)->sole();

    expect($push['aps']['event'])->toBe('end')
        ->and($push['aps']['content-state']['ended'])->toBeTrue()
        ->and($push['aps']['dismissal-date'])->toBe(now()->addHour()->getTimestamp())
        ->and(LiveActivityToken::count())->toBe(0);
})->with([
    'ended by hand' => fn (Cook $cook) => $cook->end(),
    'ended automatically' => fn (Cook $cook) => test()->artisan('cooks:end-abandoned')->run(),
]);

it('drops tokens Apple has retired', function () {
    $this->apnsResponse = Http::response(['reason' => 'Unregistered'], 410);
    $cook = Cook::factory()->create();
    LiveActivityToken::factory()->create(['cook_id' => $cook->id]);

    $this->artisan('cooks:run')->assertSuccessful();

    expect(LiveActivityToken::count())->toBe(0);
});

it('sends nothing until an APNs key is configured', function () {
    config(['services.apns.private_key_path' => null]);
    $cook = Cook::factory()->create();
    LiveActivityToken::factory()->create(['cook_id' => $cook->id]);

    $this->artisan('cooks:run')->assertSuccessful();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'push.apple.com'));
});

it('gives iOS dates in its own epoch and the time until the food is done', function () {
    $cook = Cook::factory()->create(['started_at' => now()->subHours(4)]);
    recordReadings($cook, Probe::FOOD1, collect(range(0, 6))->mapWithKeys(fn ($step) => [200 + $step * 5 => [1500 + $step * 10, 2030]])->all());

    $state = LiveActivityContent::state(new CookSnapshot($cook));
    $attributes = LiveActivityContent::attributes($cook);

    expect($attributes['startedAt'])->toEqual($cook->started_at->getTimestamp() - 978307200)
        ->and($state['estimatedDoneAt'])->toBeGreaterThan(now()->getTimestamp() - 978307200)
        ->and($state['pit'])->toBeNull()
        ->and($state['foods'][0]['label'])->toBe('Food 1');
});
