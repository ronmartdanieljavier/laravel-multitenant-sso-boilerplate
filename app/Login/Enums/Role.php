<?php

namespace App\Login\Enums;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';
    case Readonly = 'readonly';
}
