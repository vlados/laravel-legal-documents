<?php

namespace Vlados\LegalDocuments\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider as BaseProvider;
use Vlados\LegalDocuments\Filament\LegalDocumentsPlugin;

class PanelProvider extends BaseProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('admin')->path('admin')->plugin(LegalDocumentsPlugin::make())->middleware([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    }
}
