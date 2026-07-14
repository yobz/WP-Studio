<?php

namespace App\Services;

use App\Models\Site;

class SiteService
{
    public function update(Site $site, array $attributes): Site
    {
        $site->update($attributes);

        return $site;
    }

    public function delete(Site $site): void
    {
        $site->delete();
    }
}
