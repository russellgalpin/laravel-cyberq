<?php

namespace App\Http\Requests\Api;

use App\Enums\Meat;
use App\Models\Cook;
use App\Models\Probe;
use Illuminate\Foundation\Http\FormRequest;

class StoreCookRequest extends FormRequest
{
    use ChoosesProbes;

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'guru_id' => ['required', 'integer', 'exists:gurus,id'],
            'started_at' => ['nullable', 'date'],
            ...$this->probeRules(),
            'pit_target' => self::temperatureRule(),
            'food_target' => self::temperatureRule(),
        ];
    }

    /** @return list<string> */
    public function probesToUse(): array
    {
        return $this->probeIdentifiers() ?? Probe::DEFAULT_FOR_NEW_COOKS;
    }

    /**
     * Each food probe's done temperature: its own, or the one for every food
     * probe that older versions of the app send.
     *
     * @return array<string, float|null>
     */
    public function foodTargets(): array
    {
        $everyFood = $this->validated('food_target');
        $probes = collect($this->validated('probes', []))->keyBy('identifier');

        return collect($this->probesToUse())
            ->mapWithKeys(fn (string $identifier) => [$identifier => self::fahrenheit($probes->get($identifier)['target'] ?? $everyFood)])
            ->all();
    }

    public function pitTarget(): ?float
    {
        return self::fahrenheit($this->validated('pit_target'));
    }

    public function cookName(): string
    {
        if (filled($this->validated('name'))) {
            return trim($this->validated('name'));
        }

        $meats = collect($this->foods())
            ->map(fn (array $food) => Meat::tryFrom((string) $food['meat']))
            ->filter()
            ->values()
            ->all();

        return Cook::nameFor($meats);
    }

    private static function fahrenheit(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
