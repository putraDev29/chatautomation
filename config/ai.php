<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Driver
    |--------------------------------------------------------------------------
    | 'simulation' : Local rule-based intelligent NLP assistant for testing
    | 'openai'     : OpenAI Chat Completions API with Function Calling
    | 'gemini'     : Google Gemini API
    */
    'driver' => env('AI_DRIVER', 'simulation'),

    /*
    |--------------------------------------------------------------------------
    | AI API Keys and Model Configuration
    |--------------------------------------------------------------------------
    */
    'api_key' => env('AI_API_KEY', ''),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
    'max_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 500),
    'timeout' => (int) env('AI_TIMEOUT', 30),
    'max_tool_calls' => (int) env('AI_MAX_TOOL_CALLS', 5),

    /*
    |--------------------------------------------------------------------------
    | Fallback & Guardrails
    |--------------------------------------------------------------------------
    */
    'max_retries' => 2,
    'retry_delay_ms' => 500,
];
