<?php

use App\Jobs\RefreshSiteMetadataJob;
use App\Models\Site;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    Site::query()->connected()->each(fn (Site $site) => RefreshSiteMetadataJob::dispatch($site));
})->daily()->name('refresh-connected-site-metadata')->withoutOverlapping();
