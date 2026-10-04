<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'started_at' => ['sometimes', 'required', 'date'],
            'ended_at' => ['sometimes', 'nullable', 'date', 'after:'.($this->input('started_at') ?? $this->route('cook')->started_at)],
        ];
    }
}
