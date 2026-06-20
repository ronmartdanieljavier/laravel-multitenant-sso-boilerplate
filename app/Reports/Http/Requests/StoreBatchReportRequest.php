<?php

namespace App\Reports\Http\Requests;

use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreBatchReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reports' => ['required', 'array', 'min:1', 'max:50'],
            'reports.*.type' => ['required', 'string', 'max:100'],
            'reports.*.format' => ['required', new Enum(ReportFormat::class)],
            'reports.*.delivery' => ['required', new Enum(ReportDelivery::class)],
            'reports.*.parameters' => ['nullable', 'array'],
        ];
    }
}
