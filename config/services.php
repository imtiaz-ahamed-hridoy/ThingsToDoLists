<?php

$globaltixModeRaw = strtolower(trim((string) env('GLOBALTIX_MODE', 'prod')));
$globaltixMode = in_array($globaltixModeRaw, ['test', 'stg', 'staging', 'sandbox'], true) ? 'test' : 'prod';

$globaltixProd = [
    'base_url'  => env('GLOBALTIX_PROD_BASE_URL', env('GLOBALTIX_BASE_URL', 'https://sg-api.globaltix.com')),
    'api_user'  => env('GLOBALTIX_PROD_API_USER', env('GLOBALTIX_API_USER', '')),
    'api_key'   => env('GLOBALTIX_PROD_API_KEY', env('GLOBALTIX_API_KEY', '')),
    'api_agent' => env('GLOBALTIX_PROD_API_AGENT', env('GLOBALTIX_API_AGENT', '')),
];

$globaltixTest = [
    'base_url'  => env('GLOBALTIX_TEST_BASE_URL', env('GLOBALTIX_BASE_URL', 'https://stg-api.globaltix.com')),
    'api_user'  => env('GLOBALTIX_TEST_API_USER', env('GLOBALTIX_API_USER', '')),
    'api_key'   => env('GLOBALTIX_TEST_API_KEY', env('GLOBALTIX_API_KEY', '')),
    'api_agent' => env('GLOBALTIX_TEST_API_AGENT', env('GLOBALTIX_API_AGENT', '')),
];

$globaltixSelected = $globaltixMode === 'test' ? $globaltixTest : $globaltixProd;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => null, // Will be generated dynamically
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
        'redirect' => env('APPLE_REDIRECT_URI'),
    ],

    'stripe'   => [
        'secret'         => env('STRIPE_SECRET'),
        'key'            => env('STRIPE_KEY'),
        'webhook'        => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'bokun' => [
        'access_key' => env('BOKUN_ACCESS_KEY'),
        'secret_key' => env('BOKUN_SECRET_KEY'),
        'base_url' => env('BOKUN_API_URL', 'https://api.bokun.io'),
    ],

    'globaltix' => [
        'mode'            => $globaltixMode,
        'prod_base_url'   => $globaltixProd['base_url'],
        'prod_api_user'   => $globaltixProd['api_user'],
        'prod_api_key'    => $globaltixProd['api_key'],
        'prod_api_agent'  => $globaltixProd['api_agent'],
        'test_base_url'   => $globaltixTest['base_url'],
        'test_api_user'   => $globaltixTest['api_user'],
        'test_api_key'    => $globaltixTest['api_key'],
        'test_api_agent'  => $globaltixTest['api_agent'],
        'base_url'        => $globaltixSelected['base_url'],
        'api_user'        => $globaltixSelected['api_user'],
        'api_key'         => $globaltixSelected['api_key'],
        'api_agent'       => $globaltixSelected['api_agent'],
        'accept_version'  => env('GLOBALTIX_ACCEPT_VERSION',   '1.0'),
        'image_url'       => env('GLOBALTIX_IMAGE_URL',        'https://product-image.globaltix.com/live-gtImage/'),

        // Unified Notification Secret for ALL GlobalTix Webhook events
        'webhook_secret'  => env('GLOBALTIX_WEBHOOK_SECRET', ''),
        'webhook_require_signature' => (bool) env('GLOBALTIX_WEBHOOK_REQUIRE_SIGNATURE', false),
        'webhook_secrets' => [
            'BOOKING_TICKET_UPDATE' => env('GLOBALTIX_WEBHOOK_SECRET_BOOKING_TICKET_UPDATE', ''),
            'BOOKING_TRANSACTION_UPDATE' => env('GLOBALTIX_WEBHOOK_SECRET_BOOKING_TRANSACTION_UPDATE', ''),
            'TICKET_REDEEM' => env('GLOBALTIX_WEBHOOK_SECRET_TICKET_REDEEM', ''),
            'TICKET_REVOKE' => env('GLOBALTIX_WEBHOOK_SECRET_TICKET_REVOKE', ''),
            'TICKET_EXPIRED' => env('GLOBALTIX_WEBHOOK_SECRET_TICKET_EXPIRED', ''),
            'PRODUCT_INFO_UPDATE' => env('GLOBALTIX_WEBHOOK_SECRET_PRODUCT_INFO_UPDATE', ''),
            'PRICE_UPDATE' => env('GLOBALTIX_WEBHOOK_SECRET_PRICE_UPDATE', ''),
        ],
    ],

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
        'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
    ],


];
