<?php

namespace App\Enums;

enum ComponentStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
}
