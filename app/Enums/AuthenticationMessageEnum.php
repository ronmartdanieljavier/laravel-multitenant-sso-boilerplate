<?php

namespace App\Enums;

enum AuthenticationMessageEnum: string
{
    case INVALID_INVITATION = 'This invitation link is invalid or has already been used.';
    case ACCOUNT_ACTIVE = 'Your account is active. Please sign in.';
}
