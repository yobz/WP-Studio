<?php

namespace App\Enums;

enum SiteStatus: string
{
    case Connected = 'connected';
    case Syncing = 'syncing';
    case Disconnected = 'disconnected';
    case Error = 'error';
}
