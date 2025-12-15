# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2025-12-15

### Added
- **Role-based document requirements** - Documents can now be required for specific user roles
  - New `required_for_roles` JSON column on `legal_document_types` table
  - New config section `roles` with `enabled`, `available`, and `user_roles_method` options
  - Auto-detection of Spatie Permission package for available roles
- New model scopes: `requiredForRoles()`, `requiredForUser()`, `requiredForRolesPreview()`
- New model methods: `isRequiredForUser()`, `isRequiredForRoles()`, `rolesEnabled()`, `getAvailableRoles()`
- New trait methods on `HasLegalAcceptances`:
  - `getDocumentAcceptance()` - Get acceptance record for a specific document
  - `getPendingDocumentsForRoles()` - Get pending documents filtered by roles
  - `getRequiredDocumentsForRoles()` - Get required documents for specific roles
  - `needsToAcceptDocumentsForRoles()` - Check if user needs to accept documents based on roles
- Role selection UI in Filament admin (visible when `roles.enabled = true`)

### Fixed
- Filament v4 compatibility - fixed `Set` class import (moved to `Schemas\Components\Utilities`)

## [1.1.0] - 2025-12-14

### Added
- **Formal document control format** - Professional legal document layout
  - Document Control header with title, ID, and effective date
  - Revision History table showing version, date, and change reason
  - Support for English and Bulgarian translations
- New translation keys for document control and revision history sections

### Changed
- Redesigned document view with professional legal document styling
- Replaced Livewire `wire:click` with regular links for version switching

### Fixed
- Added `livewire.*` to excluded routes - prevents breaking Livewire component communication when documents are pending acceptance

## [1.0.0] - 2025-12-14

### Added
- Initial release of Laravel Legal Documents package
- **Models**: `LegalDocumentType`, `LegalDocument`, `LegalDocumentAcceptance`
- **Trait**: `HasLegalAcceptances` for User model integration
- **Middleware**: `EnsureLegalDocumentsAccepted` to block access until documents are accepted
- **Livewire Components**:
  - `AcceptDocuments` - Modal/page for users to accept pending documents
  - `ViewLegalDocument` - Public page to view legal documents with version history
- **Notification**: `LegalDocumentUpdated` - Email notification when documents are updated
- **Filament Resources** (optional):
  - `LegalDocumentTypeResource` - Manage document types (Privacy Policy, Terms, etc.)
  - `LegalDocumentResource` - Manage document versions with WordPress-style editor
- **Frontend Routes**:
  - `/legal/accept` - Accept pending documents (authenticated)
  - `/legal/{slug}` - View current version of a document
  - `/legal/{slug}/version/{version}` - View specific version of a document
- **Internationalization**: Full i18n support with English and Bulgarian translations
- **Features**:
  - Version tracking with publish workflow
  - User acceptance tracking with audit metadata (IP, user agent)
  - Summary of changes between versions
  - Re-acceptance requirement on document updates
  - Optional email notifications to users when documents are updated
