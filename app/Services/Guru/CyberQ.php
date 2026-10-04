<?php

namespace App\Services\Guru;

use App\Models\Guru;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use SimpleXMLElement;

class CyberQ
{
    /**
     * Fields the CyberQ accepts on its main page (index) and its control page,
     * mirroring the forms in its own web interface. Temperatures are sent in
     * whole or decimal degrees F, regardless of the unit shown on the device.
     */
    public const array MAIN_PAGE_FIELDS = [
        'COOK_NAME',
        'COOK_SET',
        'FOOD1_NAME',
        'FOOD1_SET',
        'FOOD2_NAME',
        'FOOD2_SET',
        'FOOD3_NAME',
        'FOOD3_SET',
        'COOK_TIMER',
    ];

    public const array CONTROL_PAGE_FIELDS = [
        'COOKHOLD',
        'TIMEOUT_ACTION',
        'ALARMDEV',
        'COOK_RAMP',
        'OPENDETECT',
        'CYCTIME',
        'PROPBAND',
    ];

    /**
     * config.xml also contains the device's Wi-Fi and SMTP credentials, so only
     * these values are ever kept from it.
     */
    private const array SETTINGS_FIELDS = [
        ...self::MAIN_PAGE_FIELDS,
        ...self::CONTROL_PAGE_FIELDS,
        'DEG_UNITS',
        'TIMER_CURR',
        'TIMER_STATUS',
    ];

    private ?DeviceStatus $status = null;

    public function __construct(private readonly Guru $guru) {}

    public function status(): DeviceStatus
    {
        return $this->status ??= new DeviceStatus($this->fetchXml('all.xml'));
    }

    public function settings(): DeviceStatus
    {
        return new DeviceStatus(Arr::only($this->fetchXml('config.xml'), self::SETTINGS_FIELDS));
    }

    public function setPitTarget(float $fahrenheit): void
    {
        $this->update(['COOK_SET' => $fahrenheit]);
    }

    public function setFoodTarget(int $probeNumber, float $fahrenheit): void
    {
        if ($probeNumber < 1 || $probeNumber > 3) {
            throw new InvalidArgumentException("The CyberQ has food probes 1 to 3, not {$probeNumber}.");
        }

        $this->update(["FOOD{$probeNumber}_SET" => $fahrenheit]);
    }

    /** @param array<string, string|int|float> $values */
    public function update(array $values): void
    {
        $unknownFields = array_diff(array_keys($values), [...self::MAIN_PAGE_FIELDS, ...self::CONTROL_PAGE_FIELDS]);

        if ($unknownFields !== []) {
            throw new InvalidArgumentException('The CyberQ does not accept: '.implode(', ', $unknownFields));
        }

        $mainPageValues = Arr::only($values, self::MAIN_PAGE_FIELDS);
        $controlPageValues = Arr::only($values, self::CONTROL_PAGE_FIELDS);

        if ($mainPageValues !== []) {
            $this->post('', $mainPageValues);
        }

        if ($controlPageValues !== []) {
            $this->post('control.htm', $controlPageValues);
        }

        $this->status = null;
    }

    /** @return array<string, string> */
    private function fetchXml(string $path): array
    {
        $body = $this->send(fn (PendingRequest $request) => $request->get($this->url($path)));

        $xml = rescue(fn () => new SimpleXMLElement($body), report: false);

        if (! $xml instanceof SimpleXMLElement) {
            throw CyberQUnreachable::for($this->guru, "{$path} was not valid XML");
        }

        return collect($xml->xpath('//*[not(*)]'))
            ->mapWithKeys(fn (SimpleXMLElement $element) => [$element->getName() => trim((string) $element)])
            ->all();
    }

    /**
     * The device's own forms submit each value alongside an underscored display
     * copy of it, so the same is done here.
     *
     * @param  array<string, string|int|float>  $values
     */
    private function post(string $path, array $values): void
    {
        $payload = collect($values)
            ->flatMap(fn ($value, string $field) => [$field => $value, "_{$field}" => $value])
            ->all();

        $this->send(fn (PendingRequest $request) => $request->asForm()->post($this->url($path), $payload));
    }

    private function send(callable $request): string
    {
        $pendingRequest = Http::withBasicAuth($this->guru->username, $this->guru->password)
            ->timeout(config('services.cyberq.timeout'))
            ->connectTimeout(config('services.cyberq.timeout'));

        try {
            return $request($pendingRequest)->throw()->body();
        } catch (ConnectionException $exception) {
            throw CyberQUnreachable::for($this->guru, 'no response', $exception);
        } catch (RequestException $exception) {
            throw CyberQUnreachable::for($this->guru, "HTTP {$exception->response->status()}", $exception);
        }
    }

    private function url(string $path): string
    {
        return "http://{$this->guru->ip}/{$path}";
    }
}
