<?php

namespace App\Reports\Enums;

use Carbon\Carbon;

enum ReportFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function isDue(?Carbon $lastDispatchedAt): bool
    {
        if ($lastDispatchedAt === null) {
            return true;
        }

        return match ($this) {
            self::Daily => $lastDispatchedAt->isBefore(now()->startOfDay()),
            self::Weekly => $lastDispatchedAt->isBefore(now()->startOfWeek()),
            self::Monthly => $lastDispatchedAt->isBefore(now()->startOfMonth()),
        };
    }
}
