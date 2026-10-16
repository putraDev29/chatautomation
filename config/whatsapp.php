<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Operation Mode
    |--------------------------------------------------------------------------
    | 'simulation' : Message simulation for local development. Never sends real WA.
    | 'production' : Connects to official Meta WhatsApp Cloud API.
    */
    'mode' => env('WHATSAPP_MODE', 'simulation'),

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    | 'simulated' or 'meta'
    */
    'provider' => env('WHATSAPP_PROVIDER', 'simulated'),

    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp Cloud API Credentials
    |--------------------------------------------------------------------------
    */
    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'bintang_mobil_verify_token_2026'),
    'app_secret' => env('WHATSAPP_APP_SECRET', ''),
    'webhook_enabled' => env('WHATSAPP_WEBHOOK_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Retry and Rate Limits
    |--------------------------------------------------------------------------
    */
    'max_retries' => 3,
    'timeout_seconds' => 15,
];
