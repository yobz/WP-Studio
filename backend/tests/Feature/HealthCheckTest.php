<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('reports ok with no authentication required', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()->assertJson([
        'data' => [
            'status' => 'ok',
            'checks' => [
                'database' => ['status' => 'ok'],
                'queue' => ['failed' => 0],
            ],
        ],
    ]);
});

it('reports degraded with a 503 when there are failed queue jobs', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'Some failure',
        'failed_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/health');

    $response->assertStatus(503)->assertJson([
        'data' => ['status' => 'degraded', 'checks' => ['queue' => ['failed' => 1]]],
    ]);
});
