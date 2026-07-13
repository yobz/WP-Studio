<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case InReview = 'in-review';
    case Published = 'published';
}
