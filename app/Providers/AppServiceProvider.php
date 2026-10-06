<?php

namespace App\Providers;

use App\Models\Gedung;
use App\Models\KategoriRuangan;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\TokenAkses;
use App\Policies\MasterPolicy;
use App\Policies\RuanganPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn ($user) => $user->hasRole('super-admin') ? true : null);
        foreach ([Gedung::class, KategoriRuangan::class, Prodi::class] as $modelMaster) {
            Gate::policy($modelMaster, MasterPolicy::class);
        }

        Gate::policy(Ruangan::class, RuanganPolicy::class);

        Sanctum::usePersonalAccessTokenModel(TokenAkses::class);
        Date::use(CarbonImmutable::class);
        Carbon::setLocale(config('app.locale'));

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
