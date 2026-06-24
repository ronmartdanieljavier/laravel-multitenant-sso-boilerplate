<?php

namespace App\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', 'unique:tenants,slug'],
            'db_host' => ['nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'db_name' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'db_username' => ['nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'read_replica_host' => ['nullable', 'string', 'max:255'],
            'read_replica_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'read_replica_username' => ['nullable', 'string', 'max:255'],
            'read_replica_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
