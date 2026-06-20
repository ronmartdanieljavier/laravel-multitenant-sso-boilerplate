<?php

namespace App\Reports\Http\Requests;

use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

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

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $reports = $this->input('reports', []);

                if (count(array_unique(array_column($reports, 'format'))) > 1) {
                    $validator->errors()->add('reports', 'All reports in a batch must use the same format.');
                }

                if (count(array_unique(array_column($reports, 'delivery'))) > 1) {
                    $validator->errors()->add('reports', 'All reports in a batch must use the same delivery mode.');
                }
            },
        ];
    }
}
