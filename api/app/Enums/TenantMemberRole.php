<?php

namespace App\Enums;

enum TenantMemberRole: string
{
    case OWNER = 'owner';
    case STAFF = 'staff';
}
