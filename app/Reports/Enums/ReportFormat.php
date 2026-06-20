<?php

namespace App\Reports\Enums;

enum ReportFormat: string
{
    case Screen = 'screen';
    case Pdf = 'pdf';
    case Excel = 'excel';
}
