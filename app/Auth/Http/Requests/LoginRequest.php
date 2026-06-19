<?php

namespace App\Auth\Http\Requests;

use App\Auth\Data\Core\LoginCredentialsCoreData;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function toData(): LoginCredentialsCoreData
    {
        return new LoginCredentialsCoreData(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
