<?php

namespace App\Reports\Enums;

enum ReportDelivery: string
{
    case Download = 'download';
    case Email = 'email';
    case S3 = 's3';
    case EmailAndS3 = 'email_and_s3';
    case None = 'none';
}
