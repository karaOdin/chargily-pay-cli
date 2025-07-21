<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chargily Pay CLI Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the Chargily Pay CLI application.
    | It supports multi-application management with separate test and live modes.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Application
    |--------------------------------------------------------------------------
    |
    | The default application to use when no specific application is selected.
    | This should match a key in the 'applications' array below.
    |
    */
    'default_application' => env('CHARGILY_DEFAULT_APP', 'main_business'),

    /*
    |--------------------------------------------------------------------------
    | Global Mode Override
    |--------------------------------------------------------------------------
    |
    | When set, this will force all applications to use the specified mode
    | regardless of their individual mode settings. Useful for development
    | or emergency situations.
    |
    | Options: null, 'test', 'live'
    |
    */
    'global_mode_override' => env('CHARGILY_GLOBAL_MODE', null),

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Global API settings that apply to all applications.
    |
    */
    'api' => [
        'base_urls' => [
            'test' => 'https://pay.chargily.net/test/api/v2',
            'live' => 'https://pay.chargily.net/api/v2',
        ],
        'timeout' => env('CHARGILY_TIMEOUT', 30),
        'retry_attempts' => env('CHARGILY_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('CHARGILY_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for caching balance and other API responses.
    |
    */
    'cache' => [
        'balance_ttl' => env('CHARGILY_BALANCE_CACHE_TTL', 300), // 5 minutes
        'customers_ttl' => env('CHARGILY_CUSTOMERS_CACHE_TTL', 900), // 15 minutes
        'products_ttl' => env('CHARGILY_PRODUCTS_CACHE_TTL', 1800), // 30 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for transaction and error logging.
    |
    */
    'logging' => [
        'enabled' => env('CHARGILY_LOGGING_ENABLED', true),
        'log_api_requests' => env('CHARGILY_LOG_API_REQUESTS', false),
        'log_sensitive_data' => env('CHARGILY_LOG_SENSITIVE_DATA', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the command-line interface appearance and behavior.
    |
    */
    'ui' => [
        'theme' => env('CHARGILY_THEME', 'default'),
        'show_mode_warnings' => env('CHARGILY_SHOW_MODE_WARNINGS', true),
        'confirm_live_actions' => env('CHARGILY_CONFIRM_LIVE_ACTIONS', true),
        'auto_refresh_balance' => env('CHARGILY_AUTO_REFRESH_BALANCE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency to use for payments when not specified.
    | Currently only 'dzd' is supported by Chargily Pay.
    |
    */
    'default_currency' => 'dzd',

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | List of currencies supported by Chargily Pay for balance display.
    |
    */
    'supported_currencies' => ['dzd', 'usd', 'eur'],

    /*
    |--------------------------------------------------------------------------
    | Default Payment Method
    |--------------------------------------------------------------------------
    |
    | The default payment method to suggest when creating payments.
    |
    */
    'default_payment_method' => 'edahabia',

    /*
    |--------------------------------------------------------------------------
    | Supported Payment Methods
    |--------------------------------------------------------------------------
    |
    | List of payment methods supported by Chargily Pay.
    |
    */
    'supported_payment_methods' => [
        'edahabia' => 'EDAHABIA',
        'cib' => 'CIB Card',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Supported locales for payment pages.
    |
    */
    'supported_locales' => [
        'ar' => 'العربية',
        'en' => 'English', 
        'fr' => 'Français',
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Limits
    |--------------------------------------------------------------------------
    |
    | Default safety limits for live mode operations.
    |
    */
    'safety_limits' => [
        'max_single_payment' => env('CHARGILY_MAX_SINGLE_PAYMENT', 100000), // DZD
        'max_daily_payments' => env('CHARGILY_MAX_DAILY_PAYMENTS', 100),
        'max_daily_volume' => env('CHARGILY_MAX_DAILY_VOLUME', 1000000), // DZD
        'require_confirmation_above' => env('CHARGILY_REQUIRE_CONFIRMATION_ABOVE', 10000), // DZD
    ],
];