<?php

namespace App\Documents\Http\Requests;

use App\Admin\Services\TenantSettingsService;
use App\Models\Central\Tenant;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreDocumentRequest extends FormRequest
{
    /** @var array<string, string[]> */
    private const TYPE_MIMES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'text' => ['text/plain'],
        'excel' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'csv' => ['text/csv', 'application/csv', 'text/comma-separated-values'],
    ];

    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->attributes->get('current_tenant');

        $service = app(TenantSettingsService::class);
        $constraints = $service->resolveUploadConstraints($tenant->id);

        $allowedTypes = $constraints['allowedTypes'];
        $maxSizes = $constraints['maxSizes'];

        $allowedMimes = [];
        foreach ($allowedTypes as $type) {
            if (isset(self::TYPE_MIMES[$type])) {
                $allowedMimes = array_merge($allowedMimes, self::TYPE_MIMES[$type]);
            }
        }

        $mimetypesRule = 'mimetypes:'.implode(',', $allowedMimes);

        $sizeRule = function (string $attribute, mixed $value, Closure $fail) use ($maxSizes): void {
            if (! ($value instanceof UploadedFile)) {
                return;
            }

            $mime = $value->getMimeType() ?? $value->getClientMimeType();
            $detectedGroup = null;

            foreach (self::TYPE_MIMES as $group => $mimes) {
                if (in_array($mime, $mimes, strict: true)) {
                    $detectedGroup = $group;
                    break;
                }
            }

            if ($detectedGroup === null || ! isset($maxSizes[$detectedGroup])) {
                return;
            }

            $maxBytes = $maxSizes[$detectedGroup] * 1024 * 1024;

            if ($value->getSize() > $maxBytes) {
                $fail("The file exceeds the maximum size of {$maxSizes[$detectedGroup]} MB for {$detectedGroup} files.");
            }
        };

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', $mimetypesRule, $sizeRule],
        ];
    }
}
