<?php

namespace App\Enums;

enum TicketAuthorType: string
{
    case User = 'user';
    case Admin = 'admin';
}
