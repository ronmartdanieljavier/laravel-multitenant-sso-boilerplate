<?php

namespace App\Reports\Http\Requests;

use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:100'],
            'format' => ['required', new Enum(ReportFormat::class)],
            'delivery' => ['required', new Enum(ReportDelivery::class)],
            'parameters' => ['nullable', 'array'],
            'batch_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
