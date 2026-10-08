<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfil;
use App\Filament\Pages\Auth\Masuk;
use App\Filament\Widgets\PeringatanInventarisasiWidget;
use App\Filament\Widgets\RincianAsetWidget;
use App\Filament\Widgets\StatistikAsetWidget;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Masuk::class)
            ->passwordReset()
            ->profile(EditProfil::class)
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()])
            ->databaseNotifications()
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): HtmlString => new HtmlString('<div class="py-2 text-center text-xs text-gray-500">'.e(config('app.name')).' v'.e(config('app.version')).'</div>'),
            )
            ->brandName('ASET FKIP')
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.auth.gaya'))
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): HtmlString => new HtmlString('<div class="aset-auth-lencana"><span>FKIP Universitas Siliwangi</span></div>'),
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_END,
                fn (): HtmlString => new HtmlString('<p class="aset-auth-kembali"><a href="'.e(url('/')).'">&larr; Kembali ke beranda</a></p>'),
            )
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                PeringatanInventarisasiWidget::class,
                StatistikAsetWidget::class,
                RincianAsetWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // App\Http\Middleware\WajibMfaAdmin::class, // nonaktif sementara: belum ada admin yang setup MFA app authentication
            ]);
    }
}
