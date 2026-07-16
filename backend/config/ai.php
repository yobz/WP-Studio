<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | Which AiClientContract implementation AppServiceProvider binds —
    | "anthropic" or "gemini". See docs/adr/0012-ai-content-generation.md.
    |
    */

    'provider' => env('AI_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | Max Output Tokens
    |--------------------------------------------------------------------------
    |
    | Bounds generation length (and cost) for the Dashboard's single-prompt
    | "Generate" action — a short-to-medium draft, not a long-form document.
    | Shared across providers so switching providers doesn't silently change
    | this product-level decision.
    |
    */

    'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),

    /*
    |--------------------------------------------------------------------------
    | Anthropic (Claude)
    |--------------------------------------------------------------------------
    */

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gemini
    |--------------------------------------------------------------------------
    */

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

];
