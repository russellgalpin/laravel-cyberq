<?php

use App\Enums\ProbeStatus;
use App\Models\Guru;
use App\Models\Probe;
use App\Services\Guru\CyberQUnreachable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->guru = Guru::factory()->create();
});

it('reads temperatures, targets and fan output from all.xml', function () {
    Http::fake(['cyberq.test/all.xml' => Http::response(cyberQXml('all.xml'))]);

    $status = $this->guru->cyberQ()->status();

    expect($status->temperature(Probe::PIT))->toBe(265.7)
        ->and($status->setPoint(Probe::PIT))->toBe(240.0)
        ->and($status->temperature(Probe::FOOD1))->toBe(170.6)
        ->and($status->setPoint(Probe::FOOD3))->toBe(190.0)
        ->and($status->fanOutput())->toBe(35)
        ->and($status->probeStatus(Probe::PIT))->toBe(ProbeStatus::Ok);
});

it('treats an unplugged probe as having no temperature', function () {
    Http::fake(['cyberq.test/all.xml' => Http::response(cyberQXml('all.xml'))]);

    $status = $this->guru->cyberQ()->status();

    expect($status->raw(Probe::FOOD2))->toBe('OPEN')
        ->and($status->temperature(Probe::FOOD2))->toBeNull()
        ->and($status->probeStatus(Probe::FOOD2))->toBe(ProbeStatus::Error);
});

it('authenticates with the guru credentials and asks the device only once per instance', function () {
    Http::fake(['cyberq.test/all.xml' => Http::response(cyberQXml('all.xml'))]);

    $cyberQ = $this->guru->cyberQ();
    $cyberQ->status();
    $cyberQ->status();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'http://cyberq.test/all.xml'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('admin:secret')));
});

it('only keeps controller settings from config.xml and never the wifi or email credentials', function () {
    Http::fake(['cyberq.test/config.xml' => Http::response(cyberQXml('config.xml'))]);

    $settings = $this->guru->cyberQ()->settings();

    expect($settings->fahrenheit('COOKHOLD'))->toBe(200.0)
        ->and($settings->integer('CYCTIME'))->toBe(6)
        ->and($settings->raw('FOOD1_NAME'))->toBe('Shoulder')
        ->and($settings->raw('TIMER_CURR'))->toBe('01:30:00')
        ->and($settings->values)->not->toHaveKeys(['WIFI_KEY', 'SSID', 'SMTP_PWD', 'IP']);
});

it('sets the pit target in degrees F on the main page, as its own form does', function () {
    Http::fake();

    $this->guru->cyberQ()->setPitTarget(250);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://cyberq.test/'
        && $request['COOK_SET'] == 250
        && $request['_COOK_SET'] == 250);
});

it('sets a food probe target', function () {
    Http::fake();

    $this->guru->cyberQ()->setFoodTarget(2, 165.5);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://cyberq.test/'
        && $request['FOOD2_SET'] == 165.5);
});

it('refuses a food probe the device does not have', function () {
    Http::fake();

    $this->guru->cyberQ()->setFoodTarget(4, 165);
})->throws(InvalidArgumentException::class);

it('sends control settings to the control page and targets to the main page', function () {
    Http::fake();

    $this->guru->cyberQ()->update([
        'COOK_SET' => 225,
        'OPENDETECT' => 0,
        'COOK_RAMP' => 1,
    ]);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->url() === 'http://cyberq.test/'
        && $request['COOK_SET'] == 225
        && ! isset($request['OPENDETECT']));
    Http::assertSent(fn (Request $request) => $request->url() === 'http://cyberq.test/control.htm'
        && $request['OPENDETECT'] == 0
        && $request['COOK_RAMP'] == 1
        && ! isset($request['COOK_SET']));
});

it('refuses to send settings it does not know about', function () {
    Http::fake();

    expect(fn () => $this->guru->cyberQ()->update(['WIFI_KEY' => 'oops']))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('reads the device again after changing it', function () {
    Http::fake(['cyberq.test/all.xml' => Http::response(cyberQXml('all.xml')), '*' => Http::response()]);

    $cyberQ = $this->guru->cyberQ();
    $cyberQ->status();
    $cyberQ->setPitTarget(230);
    $cyberQ->status();

    Http::assertSentCount(3);
});

it('reports an unreachable device', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $this->guru->cyberQ()->status();
})->throws(CyberQUnreachable::class, 'no response');

it('reports a rejected login', function () {
    Http::fake(['*' => Http::response('Unauthorized', 401)]);

    $this->guru->cyberQ()->status();
})->throws(CyberQUnreachable::class, 'HTTP 401');

it('reports a response that is not XML', function () {
    Http::fake(['*' => Http::response('<html><body>Hello</body>')]);

    $this->guru->cyberQ()->status();
})->throws(CyberQUnreachable::class, 'not valid XML');
