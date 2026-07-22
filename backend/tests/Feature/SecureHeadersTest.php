<?php

it('sets baseline security headers on every API response', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
