<?php

use App\Support\UrlDasar;
use Illuminate\Support\Facades\URL;

afterEach(function () {
    URL::forceRootUrl(null);
    URL::forceScheme('http');
});

it('membangun URL di bawah subpath APP_URL (https://supportfkip.unsil.ac.id/aset)', function () {
    config(['app.url' => 'https://supportfkip.unsil.ac.id/aset']);
    UrlDasar::terapkan();

    expect(url('/x'))->toBe('https://supportfkip.unsil.ac.id/aset/x')
        ->and(route('publik.aset', ['aset' => '01a11172-0000-7000-8000-000000000000']))->toStartWith('https://supportfkip.unsil.ac.id/aset/a/')
        ->and(route('filament.admin.auth.login'))->toBe('https://supportfkip.unsil.ac.id/aset/admin/login')
        ->and(asset('build/a.css'))->toBe('https://supportfkip.unsil.ac.id/aset/build/a.css');
});

it('tanpa subpath URL tetap di akar', function () {
    config(['app.url' => 'http://localhost']);
    UrlDasar::terapkan();

    expect(url('/x'))->not->toContain('/aset/')->toEndWith('/x');
});
