<?php

namespace App\Models\Central;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, array<string, mixed>>|null $trace
 * @property array<string, mixed>|null $request_params
 * @property array<string, mixed>|null $request_headers
 * @property array<string, mixed>|null $context
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 */
#[Fillable([
    'tenant_id', 'user_id', 'error_code', 'exception_class', 'message',
    'file', 'line', 'trace', 'request_url', 'request_method',
    'request_params', 'request_headers', 'severity', 'context', 'resolved_at',
])]
class TenantErrorLog extends Model
{
    protected function casts(): array
    {
        return [
            'trace' => 'array',
            'request_params' => 'array',
            'request_headers' => 'array',
            'context' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
