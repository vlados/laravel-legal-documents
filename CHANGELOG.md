# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-12-14

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
