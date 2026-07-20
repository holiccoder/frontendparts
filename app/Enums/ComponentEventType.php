<?php

namespace App\Enums;

enum ComponentEventType: string
{
    case View = 'view';
    case Copy = 'copy';
    case Download = 'download';
    case Scaffold = 'scaffold';
}
