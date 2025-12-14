<?php

namespace Vlados\LegalDocuments\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Vlados\LegalDocuments\Notifications\LegalDocumentUpdated;

class LegalDocument extends Model
{
    protected $fillable = [
        'legal_document_type_id',
        'version',
        'title',
        'content',
        'summary_of_changes',
        'published_at',
        'is_current',
        'requires_re_acceptance',
        'notify_users',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_current' => 'boolean',
        'requires_re_acceptance' => 'boolean',
        'notify_users' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(LegalDocumentType::class, 'legal_document_type_id');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(LegalDocumentAcceptance::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    public function scopeRequiresAcceptance(Builder $query): Builder
    {
        return $query->where('requires_re_acceptance', true);
    }

    public function isAcceptedBy(Model $user): bool
    {
        return $this->acceptances()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    public function getPreviousVersion(): ?self
    {
        return static::query()
            ->where('legal_document_type_id', $this->legal_document_type_id)
            ->where('id', '<', $this->id)
            ->published()
            ->latest('published_at')
            ->first();
    }

    public function publish(bool $notifyUsers = null): self
    {
        $shouldNotify = $notifyUsers ?? $this->notify_users;

        DB::transaction(function () {
            // Set all other versions of this type as not current
            static::query()
                ->where('legal_document_type_id', $this->legal_document_type_id)
                ->where('id', '!=', $this->id)
                ->update(['is_current' => false]);

            // Publish this version
            $this->update([
                'is_current' => true,
                'published_at' => $this->published_at ?? now(),
            ]);
        });

        if ($shouldNotify) {
            $this->notifyUsers();
        }

        return $this;
    }

    public function notifyUsers(): void
    {
        $userModel = config('legal-documents.user_model');
        $channels = config('legal-documents.notifications.channels', ['mail', 'database']);

        // Get users who need to be notified
        $query = $userModel::query();

        if ($this->requires_re_acceptance) {
            // Notify users who haven't accepted this specific version
            $acceptedUserIds = $this->acceptances()->pluck('user_id');
            $query->whereNotIn('id', $acceptedUserIds);
        }

        $notificationClass = config('legal-documents.notifications.class')
            ?? LegalDocumentUpdated::class;

        $users = $query->get();

        if (config('legal-documents.notifications.queue', true)) {
            Notification::send($users, new $notificationClass($this));
        } else {
            foreach ($users as $user) {
                $user->notify(new $notificationClass($this));
            }
        }
    }

    public function getAcceptanceCount(): int
    {
        return $this->acceptances()->count();
    }

    public function getPendingAcceptanceCount(): int
    {
        $userModel = config('legal-documents.user_model');
        $totalUsers = $userModel::count();

        return $totalUsers - $this->getAcceptanceCount();
    }
}
