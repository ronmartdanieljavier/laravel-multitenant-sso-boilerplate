<?php

namespace App\Reports\Enums;

enum ReportDelivery: string
{
    case Download = 'download';
    case Email = 'email';
    case None = 'none';
}
