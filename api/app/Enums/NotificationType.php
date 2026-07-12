<?php

namespace App\Enums;

enum NotificationType: string
{
    case PAID = 'paid';
    case ETICKET = 'eticket';
    case INVOICE = 'invoice';
    case CANCELLED = 'cancelled';
    case REMINDER = 'reminder';
}
