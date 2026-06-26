<?php

namespace App\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tenant_slug' => ['required', 'string'],
        ];
    }
}
