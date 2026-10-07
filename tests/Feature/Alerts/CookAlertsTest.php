<?php

use App\Models\Cook;
use App\Models\Probe;
use App\Models\PushDevice;
use App\Models\Reading;
use App\Support\Alerts\CookAlertMonitor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    fakeApnsKey();
    $this->freezeSecond();
    $this->device = PushDevice::factory()->create();
    $this->apnsResponse = Http::response();
    Http::fake(fn () => $this->apnsResponse);

    $this->cook = Cook::factory()->create(['name' => 'Brisket', 'started_at' => now()->subHours(3)]);
    $this->cook->useProbes([Probe::PIT, Probe::FOOD1]);
});

/**
 * One poll of the CyberQ: readings now, then the alert checks, then a minute passes.
 *
 * @param  array<string, array{0: float, 1: ?float}>  $readings  degrees F and target, keyed by probe
 */
function poll(array $readings): void
{
    $cook = test()->cook;

    foreach ($readings as $identifier => [$temperature, $target]) {
        Reading::factory()->create([
            'cook_id' => $cook->id,
            'probe_id' => $cook->guru->probes->firstWhere('identifier', $identifier)->id,
            'temperature' => (int) round($temperature * 10),
            'set_point' => $target === null ? null : (int) round($target * 10),
            'created_at' => now(),
        ]);
    }

    app(CookAlertMonitor::class)->afterReadings($cook->fresh());
    test()->travel(1)->minutes();
}

function missedPoll(): void
{
    app(CookAlertMonitor::class)->afterMissedPoll(test()->cook->fresh());
    test()->travel(1)->minutes();
}

/** @return Collection<int, array{title: string, body: string, kind: string}> */
function alertsSent(): Collection
{
    return collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn (Request $request) => $request->header('apns-push-type')[0] === 'alert')
        ->map(fn (Request $request) => [...$request['aps']['alert'], 'kind' => $request['kind']])
        ->values();
}

function pitAt(float $temperature, float $target = 240): array
{
    return [Probe::PIT => [$temperature, $target]];
}

it('warns once the pit has run hot for five minutes', function () {
    poll(pitAt(240));

    foreach (range(1, 5) as $minute) {
        poll(pitAt(300));
    }

    expect(alertsSent())->toHaveCount(0);

    poll(pitAt(300));

    expect(alertsSent())->toHaveCount(1)
        ->and(alertsSent()->sole())->toMatchArray([
            'title' => 'Pit running hot',
            'body' => 'Brisket: pit is 300°F, 60° above its 240°F target, for 5 minutes.',
            'kind' => 'pit_high',
        ]);

    Http::assertSent(fn (Request $request) => $request->url() === "https://api.sandbox.push.apple.com/3/device/{$this->device->token}"
        && $request->header('apns-topic')[0] === 'net.lrhosting.cyberq'
        && $request['aps']['interruption-level'] === 'time-sensitive'
        && $request['aps']['thread-id'] === "cook-{$this->cook->id}");
});

it('reminds once if the pit is still off fifteen minutes later, then says when it recovers', function () {
    poll(pitAt(240));

    foreach (range(1, 21) as $minute) {
        poll(pitAt(300 + $minute));
    }

    foreach (range(1, 10) as $minute) {
        poll(pitAt(330));
    }

    poll(pitAt(250));

    expect(alertsSent()->pluck('title')->all())->toBe(['Pit running hot', 'Pit still hot', 'Pit back on target'])
        ->and(alertsSent()[1]['body'])->toContain('and is still rising')
        ->and(alertsSent()[2]['body'])->toBe('Brisket: pit is 250°F (target 240°F).');
});

it('ignores the pit dipping briefly, like the lid being opened', function () {
    poll(pitAt(240));

    foreach (range(1, 4) as $minute) {
        poll(pitAt(170));
    }

    poll(pitAt(235));

    foreach (range(1, 4) as $minute) {
        poll(pitAt(170));
    }

    expect(alertsSent())->toBeEmpty();
});

it('does not call a pit that is still warming up too cold', function () {
    foreach (range(1, 30) as $minute) {
        poll(pitAt(80 + $minute * 3));
    }

    expect(alertsSent())->toBeEmpty();
});

it('warns when a pit that was up to temperature goes cold', function () {
    poll(pitAt(238));

    foreach (range(1, 6) as $minute) {
        poll(pitAt(150));
    }

    expect(alertsSent()->sole())->toMatchArray(['title' => 'Pit running cold', 'kind' => 'pit_low']);
});

it('treats raising the target like warming up again', function () {
    poll(pitAt(225, 225));

    foreach (range(1, 10) as $minute) {
        poll(pitAt(230, 300));
    }

    expect(alertsSent())->toBeEmpty();
});

it('says when the food is about an hour away, once', function () {
    foreach (range(0, 6) as $minute) {
        poll([Probe::PIT => [240, 240], Probe::FOOD1 => [180 + $minute * 0.4, 203]]);
    }

    expect(alertsSent()->sole())->toMatchArray(['title' => 'Food 1 nearly ready', 'kind' => 'food_nearly_ready'])
        ->and(alertsSent()->sole()['body'])->toMatch('/^Brisket: Food 1 is \d+°F and should reach 203°F in about \d+ minutes, around \d\d:\d\d\.$/');
});

it('does not say the food is nearly ready while it is hours away', function () {
    foreach (range(0, 6) as $minute) {
        poll([Probe::PIT => [240, 240], Probe::FOOD1 => [120 + $minute * 0.1, 203]]);
    }

    expect(alertsSent())->toBeEmpty();
});

it('says when the food is ready, once, and again if the target is raised', function () {
    poll([Probe::PIT => [240, 240], Probe::FOOD1 => [203.5, 203]]);
    poll([Probe::PIT => [240, 240], Probe::FOOD1 => [204, 203]]);
    poll([Probe::PIT => [240, 240], Probe::FOOD1 => [206, 205]]);

    expect(alertsSent()->pluck('title')->all())->toBe(['Food 1 is ready', 'Food 1 is ready'])
        ->and(alertsSent()[0]['body'])->toBe('Brisket: Food 1 is 204°F, its target. Time to take it off.');
});

it('only alerts about probes in use', function () {
    poll([Probe::PIT => [240, 240], Probe::FOOD2 => [210, 203]]);

    expect(alertsSent())->toBeEmpty();
});

it('warns after five missed polls, reminds after half an hour and says when it is back', function () {
    poll(pitAt(240));

    foreach (range(1, 4) as $minute) {
        missedPoll();
    }

    expect(alertsSent())->toBeEmpty();

    missedPoll();

    expect(alertsSent()->sole())->toMatchArray([
        'title' => 'CyberQ offline',
        'body' => 'Brisket: no readings from the CyberQ for 5 minutes. Has it switched off?',
        'kind' => 'cyberq_offline',
    ]);

    foreach (range(1, 31) as $minute) {
        missedPoll();
    }

    poll(pitAt(240));

    expect(alertsSent()->pluck('title')->all())->toBe(['CyberQ offline', 'CyberQ still offline', 'CyberQ back online'])
        ->and(alertsSent()[1]['body'])->toContain('The cook ends automatically after 12 hours without readings.')
        ->and(alertsSent()[2]['body'])->toBe('Brisket: readings resumed after 36 minutes.');
});

it('does not call the CyberQ offline before it has been switched on', function () {
    foreach (range(1, 10) as $minute) {
        missedPoll();
    }

    expect(alertsSent())->toBeEmpty();
});

it('counts missed polls from the scheduled poll', function () {
    Http::fake(fn (Request $request) => str_contains($request->url(), 'push.apple.com') ?
        Http::response() :
        throw new ConnectionException('timed out'));
    poll(pitAt(240));

    foreach (range(1, 5) as $minute) {
        $this->artisan('cooks:run')->assertSuccessful();
        $this->travel(1)->minutes();
    }

    expect(alertsSent()->sole()['title'])->toBe('CyberQ offline');
});

it('respects which alerts each phone wants', function () {
    $this->device->update(['pit_alerts' => false]);
    $foodOnly = $this->device;
    $everything = PushDevice::factory()->create();

    poll(pitAt(240));

    foreach (range(1, 6) as $minute) {
        poll(pitAt(300));
    }

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), $everything->token));
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), $foodOnly->token));
});

it('forgets phones Apple no longer recognises', function () {
    $this->apnsResponse = Http::response(['reason' => 'BadDeviceToken'], 400);
    poll([Probe::PIT => [240, 240], Probe::FOOD1 => [204, 203]]);

    expect(PushDevice::count())->toBe(0);
});
