# Laravel Legal Documents

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vlados/laravel-legal-documents.svg?style=flat-square)](https://packagist.org/packages/vlados/laravel-legal-documents)
[![License](https://img.shields.io/packagist/l/vlados/laravel-legal-documents.svg?style=flat-square)](https://packagist.org/packages/vlados/laravel-legal-documents)

A Laravel package for managing legal documents (Privacy Policy, Terms of Service, etc.) with version tracking, user acceptance management, and optional Filament admin panel integration.

## Features

- **Document Types Management** - Create and manage different types of legal documents (Privacy Policy, Terms of Service, Cookie Policy, etc.)
- **Version Control** - Track document versions with full history and publish workflow
- **User Acceptance Tracking** - Record when users accept documents with audit metadata (IP address, user agent)
- **Re-acceptance on Updates** - Optionally require users to re-accept documents when they are updated
- **Middleware Protection** - Block access to your application until required documents are accepted
- **Email Notifications** - Notify users when legal documents are updated
- **Filament Integration** - Optional admin panel with WordPress-style document editor
- **Frontend Routes** - Public pages to view legal documents with version history
- **Internationalization** - Full i18n support (English and Bulgarian included)

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- Livewire 3.x

## Installation

Install the package via Composer:

```bash
composer require vlados/laravel-legal-documents
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=legal-documents-config
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=legal-documents-migrations
php artisan migrate
```

## Configuration

After publishing, you can configure the package in `config/legal-documents.php`:

```php
return [
    // Your User model
    'user_model' => App\Models\User::class,

    // Route where users accept pending documents
    'acceptance_route' => 'legal.accept',

    // Routes accessible even with pending documents
    'excluded_routes' => [
        'logout',
        'legal.*',
        'filament.*',
        'livewire.*',
    ],

    // Notification settings
    'notifications' => [
        'channels' => ['mail', 'database'],
        'queue' => true,
    ],

    // Filament admin panel integration
    'filament' => [
        'enabled' => true,
        'navigation_group' => 'Settings',
    ],

    // Frontend routes configuration
    'frontend' => [
        'enabled' => true,
        'prefix' => 'legal',
        'middleware' => ['web'],
        'layout' => 'layouts.app',
    ],
];
```

## Setup

### 1. Add the Trait to Your User Model

Add the `HasLegalAcceptances` trait to your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Vlados\LegalDocuments\Traits\HasLegalAcceptances;

class User extends Authenticatable
{
    use HasLegalAcceptances;

    // ...
}
```

### 2. Register the Middleware

Add the middleware alias in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'legal-documents' => \Vlados\LegalDocuments\Http\Middleware\EnsureLegalDocumentsAccepted::class,
    ]);
})
```

### 3. Apply Middleware to Routes

Apply the middleware to routes that require document acceptance:

```php
// Apply to specific routes
Route::middleware(['auth', 'legal-documents'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
});

// Or apply globally in bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', [
        \Vlados\LegalDocuments\Http\Middleware\EnsureLegalDocumentsAccepted::class,
    ]);
})
```

### 4. Filament Integration (Optional)

If you're using Filament, register the plugin in your Panel provider:

```php
use Vlados\LegalDocuments\Filament\LegalDocumentsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            LegalDocumentsPlugin::make(),
        ]);
}
```

## Usage

### Creating Document Types

Using Filament admin panel or programmatically:

```php
use Vlados\LegalDocuments\Models\LegalDocumentType;

LegalDocumentType::create([
    'name' => 'Privacy Policy',
    'slug' => 'privacy-policy',
    'description' => 'Our privacy policy document',
    'is_required' => true,
    'sort_order' => 1,
]);
```

### Creating Documents

```php
use Vlados\LegalDocuments\Models\LegalDocument;

$document = LegalDocument::create([
    'legal_document_type_id' => $type->id,
    'title' => 'Privacy Policy',
    'version' => '1.0',
    'content' => '<p>Your privacy policy content...</p>',
    'summary_of_changes' => null,
    'requires_re_acceptance' => true,
    'notify_users' => true,
]);

// Publish the document
$document->publish();
```

### Publishing a Document

When you publish a document, it becomes the current version and optionally notifies users:

```php
$document->publish();
```

This will:
1. Set `published_at` to now
2. Mark as `is_current`
3. Unmark any previous current document
4. Send notifications to users (if `notify_users` is true)

### Checking User Acceptance

```php
// Check if user needs to accept any documents
if ($user->needsToAcceptDocuments()) {
    // Redirect to acceptance page
}

// Check if user has accepted a specific document type
if ($user->hasAcceptedLatest('privacy-policy')) {
    // User has accepted the latest privacy policy
}

// Get all pending documents for a user
$pendingDocuments = $user->getPendingDocuments();

// Get user's acceptance history
$history = $user->getAcceptanceHistory();
```

### Accepting Documents

```php
// Accept a single document
$user->acceptDocument($document, request()->ip(), request()->userAgent());

// Accept multiple documents
$user->acceptDocuments($documentIds, request()->ip(), request()->userAgent());
```

## Frontend Routes

The package provides these public routes (configurable via `frontend.prefix`):

| Route | Description |
|-------|-------------|
| `/legal/accept` | Page for authenticated users to accept pending documents |
| `/legal/{slug}` | View the current version of a document |
| `/legal/{slug}/version/{version}` | View a specific version of a document |

## Customization

### Publishing Views

```bash
php artisan vendor:publish --tag=legal-documents-views
```

Views will be published to `resources/views/vendor/legal-documents/`.

### Publishing Translations

```bash
php artisan vendor:publish --tag=legal-documents-lang
```

Translations will be published to `lang/vendor/legal-documents/`.

### Custom Notification

Create your own notification class and configure it:

```php
// config/legal-documents.php
'notifications' => [
    'class' => App\Notifications\CustomLegalDocumentUpdated::class,
],
```

### Custom Layout

Configure the layout used for the document view page:

```php
// config/legal-documents.php
'frontend' => [
    'layout' => 'layouts.app', // Your layout file
],
```

## API Reference

### HasLegalAcceptances Trait

| Method | Description |
|--------|-------------|
| `legalAcceptances()` | HasMany relationship to acceptances |
| `hasAcceptedDocument(LegalDocument $document)` | Check if user accepted a specific document |
| `hasAcceptedLatest(string $typeSlug)` | Check if user accepted latest version of a document type |
| `getPendingDocuments()` | Get all documents pending acceptance |
| `needsToAcceptDocuments()` | Check if user has pending documents |
| `acceptDocument($document, $ip, $userAgent)` | Accept a single document |
| `acceptDocuments($ids, $ip, $userAgent)` | Accept multiple documents |
| `getAcceptanceHistory()` | Get user's acceptance history |

### LegalDocument Model

| Method | Description |
|--------|-------------|
| `publish()` | Publish the document and notify users |
| `scopeCurrent($query)` | Query scope for current documents |
| `scopePublished($query)` | Query scope for published documents |
| `scopeRequiresAcceptance($query)` | Query scope for documents requiring acceptance |

### LegalDocumentType Model

| Method | Description |
|--------|-------------|
| `currentDocument` | Relationship to current document |
| `documents` | Relationship to all documents |
| `scopeRequired($query)` | Query scope for required document types |
| `scopeOrdered($query)` | Query scope for ordered by sort_order |

## Events

The package dispatches the following events:

- Document published → Sends `LegalDocumentUpdated` notification to users

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
