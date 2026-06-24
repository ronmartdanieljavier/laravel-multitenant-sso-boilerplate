<?php

namespace App\Admin\Http\Requests;

use App\Auth\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'apps' => ['nullable', 'array'],
            'apps.*.app_id' => ['required', 'integer', Rule::exists('apps', 'id')],
            'apps.*.role' => ['required', Rule::enum(Role::class)],
            'apps.*.tenant_ids' => ['nullable', 'array'],
            'apps.*.tenant_ids.*' => ['integer', Rule::exists('tenants', 'id')],
        ];
    }
}
