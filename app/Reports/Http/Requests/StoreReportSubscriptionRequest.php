<?php

namespace App\Reports\Http\Requests;

use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:100'],
            'format' => ['required', Rule::enum(ReportFormat::class)],
            'frequency' => ['required', Rule::enum(ReportFrequency::class)],
            'delivery' => ['required', Rule::enum(ReportDelivery::class)],
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['email'],
            's3_path' => ['nullable', 'string', 'max:500'],
        ];
    }
}
