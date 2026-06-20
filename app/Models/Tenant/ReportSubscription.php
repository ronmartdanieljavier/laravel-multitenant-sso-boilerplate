<?php

namespace App\Models\Tenant;

use App\Reports\Enums\ReportDelivery;
use App\Reports\Enums\ReportFormat;
use App\Reports\Enums\ReportFrequency;
use Database\Factories\Reports\ReportSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property ReportFormat $format
 * @property ReportFrequency $frequency
 * @property ReportDelivery $delivery
 * @property string[]|null $recipients
 * @property string|null $s3_path
 * @property bool $is_active
 * @property Carbon|null $last_dispatched_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReportSubscription extends Model
{
    /** @use HasFactory<ReportSubscriptionFactory> */
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'type',
        'format',
        'frequency',
        'delivery',
        'recipients',
        's3_path',
        'is_active',
        'last_dispatched_at',
    ];

    protected static function newFactory(): ReportSubscriptionFactory
    {
        return ReportSubscriptionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'format' => ReportFormat::class,
            'frequency' => ReportFrequency::class,
            'delivery' => ReportDelivery::class,
            'recipients' => 'array',
            'is_active' => 'boolean',
            'last_dispatched_at' => 'datetime',
        ];
    }

    public function isDue(): bool
    {
        return $this->frequency->isDue($this->last_dispatched_at);
    }
}
