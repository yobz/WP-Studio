<?php

namespace App\Services\ContentSync\Enums;

enum SyncOutcome: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Skipped = 'skipped';
}
