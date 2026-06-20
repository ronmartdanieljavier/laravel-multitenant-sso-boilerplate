<?php

namespace App\Reports\Http\Requests;

use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'max:100'],
            'format' => ['sometimes', Rule::enum(ReportFormat::class)],
            'frequency' => ['sometimes', Rule::enum(ReportFrequency::class)],
            'delivery' => ['sometimes', Rule::enum(ReportDelivery::class)],
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['email'],
            's3_path' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
