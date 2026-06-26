<?php

namespace App\Models\Central;

use App\TenantJobs\Enums\TenantJobStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tracking_id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $job_class
 * @property string $display_name
 * @property TenantJobStatus $status
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class TenantJobRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'tracking_id',
        'tenant_id',
        'user_id',
        'job_class',
        'display_name',
        'status',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'status' => TenantJobStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
