<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Applications Configuration
    |--------------------------------------------------------------------------
    |
    | This file stores the configuration for multiple Chargily Pay applications.
    | Each application can have separate test and live mode settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Applications
    |--------------------------------------------------------------------------
    |
    | Array of configured applications. Each application should have:
    | - name: Human-readable name
    | - test: Test mode configuration
    | - live: Live mode configuration  
    | - current_mode: Current active mode ('test' or 'live')
    | - settings: Application-specific settings
    |
    */
    'applications' => [
        // Default main application
        'main' => [
            'name' => 'Main Business',
            'test' => [
                'api_key' => env('CHARGILY_MAIN_TEST_KEY'),
                'webhook_url' => env('CHARGILY_MAIN_TEST_WEBHOOK'),
                'default_success_url' => env('CHARGILY_MAIN_TEST_SUCCESS_URL'),
                'default_failure_url' => env('CHARGILY_MAIN_TEST_FAILURE_URL'),
                'balance_cache' => null,
                'last_balance_check' => null,
            ],
            'live' => [
                'api_key' => env('CHARGILY_MAIN_LIVE_KEY'),
                'webhook_url' => env('CHARGILY_MAIN_LIVE_WEBHOOK'),
                'default_success_url' => env('CHARGILY_MAIN_LIVE_SUCCESS_URL'),
                'default_failure_url' => env('CHARGILY_MAIN_LIVE_FAILURE_URL'),
                'balance_cache' => null,
                'last_balance_check' => null,
            ],
            'current_mode' => 'test',
            'settings' => [
                'default_currency' => 'dzd',
                'auto_expire_minutes' => 30,
                'default_payment_method' => 'edahabia',
                'require_confirmation' => true,
                'enable_notifications' => true,
                'safety_limits' => [
                    'max_single_payment' => 100000,
                    'max_daily_payments' => 50,
                    'max_daily_volume' => 500000,
                ],
                'webhook_settings' => [
                    'enabled' => true,
                    'verify_signature' => true,
                    'retry_failed' => true,
                    'max_retries' => 3,
                ],
            ],
            'created_at' => now()->toISOString(),
            'last_used' => now()->toISOString(),
            'metadata' => [
                'description' => 'Default application for main business operations',
                'tags' => ['main', 'primary'],
            ],
        ],

        // Example additional application (commented out by default)
        /*
        'ecommerce' => [
            'name' => 'E-commerce Store',
            'test' => [
                'api_key' => env('CHARGILY_ECOMMERCE_TEST_KEY'),
                'webhook_url' => env('CHARGILY_ECOMMERCE_TEST_WEBHOOK'),
                'default_success_url' => env('CHARGILY_ECOMMERCE_TEST_SUCCESS_URL'),
                'default_failure_url' => env('CHARGILY_ECOMMERCE_TEST_FAILURE_URL'),
                'balance_cache' => null,
                'last_balance_check' => null,
            ],
            'live' => [
                'api_key' => env('CHARGILY_ECOMMERCE_LIVE_KEY'),
                'webhook_url' => env('CHARGILY_ECOMMERCE_LIVE_WEBHOOK'),
                'default_success_url' => env('CHARGILY_ECOMMERCE_LIVE_SUCCESS_URL'),
                'default_failure_url' => env('CHARGILY_ECOMMERCE_LIVE_FAILURE_URL'),
                'balance_cache' => null,
                'last_balance_check' => null,
            ],
            'current_mode' => 'test',
            'settings' => [
                'default_currency' => 'dzd',
                'auto_expire_minutes' => 30,
                'default_payment_method' => 'edahabia',
                'require_confirmation' => true,
                'enable_notifications' => true,
                'safety_limits' => [
                    'max_single_payment' => 50000,
                    'max_daily_payments' => 100,
                    'max_daily_volume' => 1000000,
                ],
            ],
            'created_at' => now()->toISOString(),
            'last_used' => null,
            'metadata' => [
                'description' => 'Online store payment processing',
                'tags' => ['ecommerce', 'store'],
            ],
        ],
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Templates
    |--------------------------------------------------------------------------
    |
    | Pre-defined application templates for quick setup of new applications.
    |
    */
    'templates' => [
        'basic' => [
            'name' => 'Basic Application',
            'description' => 'Simple application template with standard settings',
            'settings' => [
                'default_currency' => 'dzd',
                'auto_expire_minutes' => 30,
                'default_payment_method' => 'edahabia',
                'require_confirmation' => true,
                'enable_notifications' => true,
            ],
        ],
        
        'ecommerce' => [
            'name' => 'E-commerce Template',
            'description' => 'Template optimized for online stores',
            'settings' => [
                'default_currency' => 'dzd',
                'auto_expire_minutes' => 60,
                'default_payment_method' => 'edahabia',
                'require_confirmation' => false,
                'enable_notifications' => true,
                'safety_limits' => [
                    'max_single_payment' => 50000,
                    'max_daily_payments' => 200,
                    'max_daily_volume' => 2000000,
                ],
            ],
        ],

        'subscription' => [
            'name' => 'Subscription Template',
            'description' => 'Template for subscription-based services',
            'settings' => [
                'default_currency' => 'dzd',
                'auto_expire_minutes' => 120,
                'default_payment_method' => 'edahabia',
                'require_confirmation' => false,
                'enable_notifications' => true,
                'safety_limits' => [
                    'max_single_payment' => 20000,
                    'max_daily_payments' => 50,
                    'max_daily_volume' => 500000,
                ],
            ],
        ],

        'enterprise' => [
            'name' => 'Enterprise Template',
            'description' => 'Template for large-scale enterprise applications',
            'settings' => [
                'default_currency' => 'dzd',
                'auto_expire_minutes' => 45,
                'default_payment_method' => 'edahabia',
                'require_confirmation' => true,
                'enable_notifications' => true,
                'safety_limits' => [
                    'max_single_payment' => 500000,
                    'max_daily_payments' => 1000,
                    'max_daily_volume' => 10000000,
                ],
                'webhook_settings' => [
                    'enabled' => true,
                    'verify_signature' => true,
                    'retry_failed' => true,
                    'max_retries' => 5,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Schema Version
    |--------------------------------------------------------------------------
    |
    | Version of the configuration schema for migration purposes.
    |
    */
    'schema_version' => '1.0.0',
];