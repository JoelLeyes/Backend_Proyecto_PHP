<?php

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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'notificaciones' => [
        'url'   => env('NOTIF_SERVICE_URL', ''),
        'token' => env('NOTIF_SERVICE_TOKEN', ''),
    ],

    'atlas_logs' => [
        'enabled'       => env('ATLAS_LOGS_ENABLED', false),
        'mongodb_uri'   => env('ATLAS_MONGODB_URI', ''),
        'database'      => env('ATLAS_LOGS_DATABASE', 'proyecto2026_logs'),
        'collection'    => env('ATLAS_LOGS_COLLECTION', 'logs'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'secret'    => env('PAYPAL_SECRET', ''),
        'mode'      => env('PAYPAL_MODE', 'sandbox'),
    ],

    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')),

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect'      => env('GOOGLE_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost:8000'), '/').'/auth/google/callback'),
    ],

    'github' => [
        'client_id'     => env('GITHUB_CLIENT_ID', ''),
        'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
        'redirect'      => env('GITHUB_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost:8000'), '/').'/auth/github/callback'),
    ],

    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID', ''),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET', ''),
        'redirect'      => env('FACEBOOK_REDIRECT_URI', rtrim(env('APP_URL', 'http://localhost:8000'), '/').'/auth/facebook/callback'),
    ],

    'livekit' => [
        'url'    => env('LIVEKIT_URL', ''),
        'key'    => env('LIVEKIT_API_KEY', ''),
        'secret' => env('LIVEKIT_API_SECRET', ''),
    ],

];
