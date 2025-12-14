<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The fully qualified class name of your User model.
    |
    */
    'user_model' => App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Acceptance Route
    |--------------------------------------------------------------------------
    |
    | The route name where users will be redirected to accept pending documents.
    |
    */
    'acceptance_route' => 'legal.accept',

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | Routes that should be accessible even when documents are pending.
    | Supports wildcard patterns (e.g., 'legal.*').
    |
    */
    'excluded_routes' => [
        'logout',
        'legal.*',
        'filament.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Configure how users are notified when legal documents are updated.
    |
    */
    'notifications' => [
        // Notification channels: 'mail', 'database', or both
        'channels' => ['mail', 'database'],

        // Queue notifications for better performance
        'queue' => true,

        // Custom notification class (optional)
        'class' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Integration
    |--------------------------------------------------------------------------
    |
    | Configure the optional Filament admin panel integration.
    |
    */
    'filament' => [
        'enabled' => true,
        'navigation_group' => 'Settings',
        'navigation_sort' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Configure middleware behavior.
    |
    */
    'middleware' => [
        // Alias for the middleware
        'alias' => 'legal-documents',

        // Automatically apply to web routes
        'auto_apply' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Tracking
    |--------------------------------------------------------------------------
    |
    | Track additional data when users accept documents.
    |
    */
    'audit' => [
        'track_ip' => true,
        'track_user_agent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend Routes
    |--------------------------------------------------------------------------
    |
    | Configure the public-facing legal document pages.
    |
    */
    'frontend' => [
        // Enable/disable frontend routes
        'enabled' => true,

        // URL prefix for legal document pages (e.g., /legal/privacy-policy)
        'prefix' => 'legal',

        // Middleware to apply to frontend routes
        'middleware' => ['web'],

        // Layout to use for the document view page
        'layout' => 'layouts.app',
    ],
];
