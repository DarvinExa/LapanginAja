<?php

namespace App\Enums;

enum UserRole: string
{
    case PLAYER = 'player';
    case OWNER = 'owner';
    case STAFF = 'staff';
    case SUPER_ADMIN = 'super_admin';
}
