<?php

namespace App\Http\Requests\Api;

use App\Enums\Meat;
use App\Models\Probe;
use App\Support\ControllerSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'meat' => ['nullable', Rule::enum(Meat::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'guru_id' => ['required', 'integer', 'exists:gurus,id'],
            'started_at' => ['nullable', 'date'],
            'probes' => ['sometimes', 'array', 'min:1'],
            'probes.*' => ['string', 'distinct', Rule::in(Probe::TEMPERATURES)],
            'pit_target' => ['nullable', 'numeric', 'min:'.ControllerSettings::MINIMUM_FAHRENHEIT, 'max:'.ControllerSettings::MAXIMUM_FAHRENHEIT],
            'food_target' => ['nullable', 'numeric', 'min:'.ControllerSettings::MINIMUM_FAHRENHEIT, 'max:'.ControllerSettings::MAXIMUM_FAHRENHEIT],
        ];
    }
}
