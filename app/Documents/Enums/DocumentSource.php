<?php

namespace App\Documents\Enums;

enum DocumentSource: string
{
    case Upload = 'upload';
    case Report = 'report';
    case Subscription = 'subscription';
}
