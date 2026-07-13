<?php

namespace App\Services;

use App\Events\SiteConnected;
use App\Models\Site;
use App\Models\Workspace;

/**
 * Controllers orchestrate (validate via Form Request, call this,
 * return a Resource); this is where the one piece of real business
 * logic beyond a plain `Model::create()` lives — dispatching
 * `SiteConnected` (defined in Milestone 6 as a placeholder, dispatched
 * for the first time here).
 */
class SiteService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Workspace $workspace, array $attributes): Site
    {
        /** @var Site $site */
        $site = $workspace->sites()->create($attributes);

        SiteConnected::dispatch($site);

        return $site;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Site $site, array $attributes): Site
    {
        $site->update($attributes);

        return $site;
    }

    public function delete(Site $site): void
    {
        // Soft delete (Site uses SoftDeletes) — recoverable, not
        // destructive. See docs/adr/0005-domain-model.md.
        $site->delete();
    }
}
