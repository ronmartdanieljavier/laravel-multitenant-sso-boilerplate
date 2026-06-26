<?php

namespace App\TenantJobs\Enums;

enum TenantJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
