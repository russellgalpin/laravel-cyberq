<?php

namespace App\Http\Requests\Api;

use App\Enums\Meat;
use App\Models\Probe;
use App\Support\ControllerSettings;
use Illuminate\Validation\Rule;

/**
 * Probes can be sent as identifiers (["COOK_TEMP", "FOOD1_TEMP"]) or, to say
 * what each food probe is in, as objects:
 * [{"identifier": "FOOD1_TEMP", "meat": "pulled_pork", "name": "Big one", "target": 203}].
 */
trait ChoosesProbes
{
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('probes'))) {
            return;
        }

        $this->merge([
            'probes' => array_map(
                fn (mixed $probe) => is_string($probe) ? ['identifier' => $probe] : $probe,
                $this->input('probes'),
            ),
        ]);
    }

    /** @return array<string, list<mixed>> */
    protected function probeRules(): array
    {
        return [
            'probes' => ['sometimes', 'array', 'min:1'],
            'probes.*' => ['array'],
            'probes.*.identifier' => ['required', 'string', 'distinct', Rule::in(Probe::TEMPERATURES)],
            'probes.*.meat' => ['nullable', Rule::enum(Meat::class)],
            'probes.*.name' => ['nullable', 'string', 'max:60'],
            'probes.*.target' => self::temperatureRule(),
        ];
    }

    /** @return list<string>|null */
    public function probeIdentifiers(): ?array
    {
        $probes = $this->validated('probes');

        return $probes === null ? null : array_column($probes, 'identifier');
    }

    /**
     * What each food probe is in, for the probes sent with a meat or name.
     *
     * @return array<string, array{meat: ?string, name: ?string}>
     */
    public function foods(): array
    {
        return collect($this->validated('probes', []))
            ->filter(fn (array $probe) => array_key_exists('meat', $probe) || array_key_exists('name', $probe))
            ->mapWithKeys(fn (array $probe) => [$probe['identifier'] => [
                'meat' => $probe['meat'] ?? null,
                'name' => $probe['name'] ?? null,
            ]])
            ->all();
    }

    /** @return list<mixed> */
    protected static function temperatureRule(): array
    {
        return ['nullable', 'numeric', 'min:'.ControllerSettings::MINIMUM_FAHRENHEIT, 'max:'.ControllerSettings::MAXIMUM_FAHRENHEIT];
    }
}
