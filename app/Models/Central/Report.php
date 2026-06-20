<?php

namespace App\Models\Central;

use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportStatus;
use Database\Factories\Reports\ReportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property int|null $tenant_id
 * @property string $type
 * @property ReportFormat $format
 * @property ReportDelivery $delivery
 * @property ReportStatus $status
 * @property array<string, mixed>|null $parameters
 * @property string|null $file_path
 * @property string|null $error_message
 * @property string|null $batch_id
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'type',
        'format',
        'delivery',
        'status',
        'parameters',
        'file_path',
        'error_message',
        'batch_id',
        'started_at',
        'completed_at',
    ];

    protected static function newFactory(): ReportFactory
    {
        return ReportFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'format' => ReportFormat::class,
            'delivery' => ReportDelivery::class,
            'parameters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
