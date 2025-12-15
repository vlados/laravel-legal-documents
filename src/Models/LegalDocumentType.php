<?php

namespace Vlados\LegalDocuments\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LegalDocumentType extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_required',
        'required_for_roles',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'required_for_roles' => 'array',
        'sort_order' => 'integer',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(LegalDocument::class);
    }

    public function currentDocument(): HasOne
    {
        return $this->hasOne(LegalDocument::class)
            ->where('is_current', true)
            ->whereNotNull('published_at');
    }

    public function latestDocument(): HasOne
    {
        return $this->hasOne(LegalDocument::class)
            ->latest('published_at');
    }

    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope for documents required for specific roles.
     */
    public function scopeRequiredForRoles(Builder $query, array $roleNames): Builder
    {
        return $query->where(function (Builder $q) use ($roleNames) {
            foreach ($roleNames as $roleName) {
                $q->orWhereJsonContains('required_for_roles', $roleName);
            }
        });
    }

    /**
     * Scope for documents required for a user (global OR role-based).
     * Falls back to just is_required if roles are disabled.
     */
    public function scopeRequiredForUser(Builder $query, ?Model $user = null): Builder
    {
        // If roles are not enabled, just use is_required
        if (! static::rolesEnabled()) {
            return $query->where('is_required', true);
        }

        return $query->where(function (Builder $q) use ($user) {
            // Always include globally required documents
            $q->where('is_required', true);

            // If user has roles, also include role-specific documents
            if ($user !== null) {
                $roleNames = static::getUserRoles($user);
                if (! empty($roleNames)) {
                    $q->orWhere(function (Builder $subQ) use ($roleNames) {
                        foreach ($roleNames as $roleName) {
                            $subQ->orWhereJsonContains('required_for_roles', $roleName);
                        }
                    });
                }
            }
        });
    }

    /**
     * Scope for documents required for specific roles (for preview/onboarding).
     * Combines global requirements with role-specific ones.
     */
    public function scopeRequiredForRolesPreview(Builder $query, array $roleNames): Builder
    {
        return $query->where(function (Builder $q) use ($roleNames) {
            // Always include globally required documents
            $q->where('is_required', true);

            // Also include role-specific documents
            if (! empty($roleNames)) {
                $q->orWhere(function (Builder $subQ) use ($roleNames) {
                    foreach ($roleNames as $roleName) {
                        $subQ->orWhereJsonContains('required_for_roles', $roleName);
                    }
                });
            }
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getCurrentVersion(): ?LegalDocument
    {
        return $this->currentDocument;
    }

    /**
     * Check if this document type is required for a specific user.
     */
    public function isRequiredForUser(?Model $user = null): bool
    {
        // Globally required
        if ($this->is_required) {
            return true;
        }

        // If roles not enabled, only is_required matters
        if (! static::rolesEnabled()) {
            return false;
        }

        // Check role-based requirement
        if ($user !== null && ! empty($this->required_for_roles)) {
            $userRoles = static::getUserRoles($user);

            return ! empty(array_intersect($userRoles, $this->required_for_roles));
        }

        return false;
    }

    /**
     * Check if this document type is required for specific roles.
     */
    public function isRequiredForRoles(array $roleNames): bool
    {
        // Globally required
        if ($this->is_required) {
            return true;
        }

        // Check role-based requirement
        if (! empty($this->required_for_roles)) {
            return ! empty(array_intersect($roleNames, $this->required_for_roles));
        }

        return false;
    }

    /**
     * Check if role-based requirements are enabled.
     */
    public static function rolesEnabled(): bool
    {
        return config('legal-documents.roles.enabled', false);
    }

    /**
     * Get user roles using the configured method.
     */
    protected static function getUserRoles(Model $user): array
    {
        $method = config('legal-documents.roles.user_roles_method', 'getRoleNames');

        if (method_exists($user, $method)) {
            $roles = $user->{$method}();

            return $roles instanceof \Illuminate\Support\Collection ? $roles->toArray() : (array) $roles;
        }

        return [];
    }

    /**
     * Get available roles for UI selection.
     */
    public static function getAvailableRoles(): array
    {
        // First check config
        $configRoles = config('legal-documents.roles.available', []);
        if (! empty($configRoles)) {
            return $configRoles;
        }

        // Auto-detect from Spatie Permission if available
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            return \Spatie\Permission\Models\Role::pluck('name', 'name')->toArray();
        }

        return [];
    }
}
