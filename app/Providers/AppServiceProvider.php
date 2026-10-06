<?php

namespace App\Providers;

use App\Contracts\PenyimpananBerkas;
use App\Models\Aset;
use App\Models\DbrVersi;
use App\Models\Gedung;
use App\Models\InventarisasiRuangan;
use App\Models\KategoriRuangan;
use App\Models\KodefikasiBarang;
use App\Models\Mutasi;
use App\Models\Peminjaman;
use App\Models\PeriodeInventarisasi;
use App\Models\Prodi;
use App\Models\Ruangan;
use App\Models\TiketPemeliharaan;
use App\Models\TokenAkses;
use App\Models\UsulanPenghapusan;
use App\Policies\AsetPolicy;
use App\Policies\DbrVersiPolicy;
use App\Policies\MasterPolicy;
use App\Policies\MutasiPolicy;
use App\Policies\PeminjamanPolicy;
use App\Policies\PeriodeInventarisasiPolicy;
use App\Policies\RuanganPolicy;
use App\Policies\TiketPemeliharaanPolicy;
use App\Policies\UsulanPenghapusanPolicy;
use App\Services\TautanEksternal;
use App\Support\PemicuNotifikasi;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PenyimpananBerkas::class, fn () => match (config('berkas.mode')) {
            'tautan' => new TautanEksternal,
            default => throw new \RuntimeException('BERKAS_MODE tidak dikenal: '.config('berkas.mode')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn ($user) => $user->hasRole('super-admin') ? true : null);
        foreach ([Gedung::class, KategoriRuangan::class, Prodi::class, KodefikasiBarang::class] as $modelMaster) {
            Gate::policy($modelMaster, MasterPolicy::class);
        }

        RateLimiter::for('lookup', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('api-klien', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->currentAccessToken()?->getKey() ?? $request->ip()));
        RateLimiter::for('lapor', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        Gate::policy(Ruangan::class, RuanganPolicy::class);
        Gate::policy(Aset::class, AsetPolicy::class);
        Gate::policy(Mutasi::class, MutasiPolicy::class);
        Gate::policy(Peminjaman::class, PeminjamanPolicy::class);
        Gate::policy(DbrVersi::class, DbrVersiPolicy::class);
        Gate::policy(UsulanPenghapusan::class, UsulanPenghapusanPolicy::class);
        Gate::policy(PeriodeInventarisasi::class, PeriodeInventarisasiPolicy::class);
        Gate::policy(InventarisasiRuangan::class, PeriodeInventarisasiPolicy::class);
        Gate::policy(TiketPemeliharaan::class, TiketPemeliharaanPolicy::class);

        PemicuNotifikasi::daftarkan();

        Sanctum::usePersonalAccessTokenModel(TokenAkses::class);
        Date::use(CarbonImmutable::class);
        Carbon::setLocale(config('app.locale'));

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
