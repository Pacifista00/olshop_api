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
    'app' => [
        'url' => env('APP_URL'),
    ],

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
    'biteship' => [
        'key' => env('BITESHIP_API_KEY'),
        'origin' => env('ORIGIN_AREA_ID'),
        'base_url' => env('BITESHIP_BASE_URL'),

        'shipper_name' => env('BITESHIP_SHIPPER_NAME'),
        'shipper_phone' => env('BITESHIP_SHIPPER_PHONE'),
        'shipper_email' => env('BITESHIP_SHIPPER_EMAIL'),
        'shipper_organization' => env('BITESHIP_SHIPPER_ORGANIZATION'),

        'origin_name' => env('BITESHIP_ORIGIN_NAME'),
        'origin_phone' => env('BITESHIP_ORIGIN_PHONE'),
        'origin_address' => env('BITESHIP_ORIGIN_ADDRESS'),
        'origin_postal_code' => env('BITESHIP_ORIGIN_POSTAL_CODE'),
    ],
    'brevo' => [
        'key' => env('BREVO_API_KEY'),
    ],

];
