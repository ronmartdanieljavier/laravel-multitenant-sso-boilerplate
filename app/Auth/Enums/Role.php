<?php

namespace App\Auth\Enums;

enum Role: string
{
    case Admin = 'admin';
    case User = 'user';
    case Readonly = 'readonly';
}
