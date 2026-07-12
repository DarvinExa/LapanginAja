<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case SENT = 'sent';
    case FAILED = 'failed';
}
