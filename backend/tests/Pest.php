<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every Feature test gets a real, migrated SQLite database
| (RefreshDatabase) — this milestone's endpoints are thin wrappers
| around real Eloquent queries, so "does the query work" is worth
| testing against an actual (test) database, not a mock.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');
