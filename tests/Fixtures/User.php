<?php

namespace Vlados\LegalDocuments\Tests\Fixtures;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Vlados\LegalDocuments\Traits\HasLegalAcceptances;

class User extends Authenticatable implements HasLocalePreference
{
    use HasLegalAcceptances;
    use Notifiable;

    protected $guarded = [];

    public function getRoleNames(): \Illuminate\Support\Collection
    {
        return collect($this->getAttribute('fixture_roles') ?? []);
    }

    public function preferredLocale(): string
    {
        return $this->locale ?? 'en';
    }
}
