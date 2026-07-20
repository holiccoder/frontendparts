<?php

namespace App\Enums;

enum ComponentLevel: string
{
    case Element = 'element';
    case Block = 'block';
    case Section = 'section';
    case Page = 'page';
}
