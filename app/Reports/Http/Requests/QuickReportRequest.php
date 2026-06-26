<?php

namespace App\Reports\Http\Requests;

use App\Reports\Enums\ReportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:100'],
            'format' => ['required', Rule::enum(ReportFormat::class)],
        ];
    }
}
