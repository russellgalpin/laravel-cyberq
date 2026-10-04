<?php

namespace App\Http\Requests\Api;

use App\Models\Probe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'started_at' => ['sometimes', 'required', 'date'],
            'probes' => ['sometimes', 'array', 'min:1'],
            'probes.*' => ['string', 'distinct', Rule::in(Probe::TEMPERATURES)],
            'ended_at' => ['sometimes', 'nullable', 'date', 'after:'.($this->input('started_at') ?? $this->route('cook')->started_at)],
        ];
    }
}
