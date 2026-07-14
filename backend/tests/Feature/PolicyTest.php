<?php

use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\SitePolicy;

it('lets a workspace member view a site, but not an outsider', function () {
    $workspace = Workspace::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $workspace->users()->attach($member, ['role' => WorkspaceRole::Member->value]);
    $site = Site::factory()->for($workspace)->create();

    $policy = new SitePolicy;

    expect($policy->view($member, $site))->toBeTrue()
        ->and($policy->view($outsider, $site))->toBeFalse();
});

it('only lets an owner or admin create or update a site, not a plain member', function () {
    $workspace = Workspace::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $workspace->users()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->users()->attach($admin, ['role' => WorkspaceRole::Admin->value]);
    $workspace->users()->attach($member, ['role' => WorkspaceRole::Member->value]);
    $site = Site::factory()->for($workspace)->create();

    $policy = new SitePolicy;

    expect($policy->create($owner, $workspace))->toBeTrue()
        ->and($policy->create($admin, $workspace))->toBeTrue()
        ->and($policy->create($member, $workspace))->toBeFalse()
        ->and($policy->update($owner, $site))->toBeTrue()
        ->and($policy->update($member, $site))->toBeFalse();
});

it('only lets an owner force-delete or restore a site', function () {
    $workspace = Workspace::factory()->create();
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $workspace->users()->attach($owner, ['role' => WorkspaceRole::Owner->value]);
    $workspace->users()->attach($admin, ['role' => WorkspaceRole::Admin->value]);
    $site = Site::factory()->for($workspace)->create();

    $policy = new SitePolicy;

    expect($policy->forceDelete($owner, $site))->toBeTrue()
        ->and($policy->forceDelete($admin, $site))->toBeFalse()
        ->and($policy->restore($owner, $site))->toBeTrue()
        ->and($policy->restore($admin, $site))->toBeFalse();
});
