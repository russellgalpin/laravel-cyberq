<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'guru_id' => ['required', 'integer', 'exists:gurus,id'],
            'started_at' => ['nullable', 'date'],
        ];
    }
}
