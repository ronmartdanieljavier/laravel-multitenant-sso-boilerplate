<?php

namespace App\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelectAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string'],
        ];
    }
}
