<?php

it('menampilkan landing page dengan tautan ke panduan peran publik', function () {
    $res = $this->get('/')->assertOk()->assertSee('SIMAN FKIP')->assertSee('Panduan pengguna per peran');

    foreach (['umum', 'civitas', 'pic', 'admin', 'pejabat', 'pimpinan'] as $peran) {
        $res->assertSee("panduan/{$peran}.html", false);
        expect(public_path("panduan/{$peran}.html"))->toBeFile();
    }
});

it('menyembunyikan tautan panduan super admin dari landing page', function () {
    $this->get('/')->assertOk()->assertDontSee('panduan/super.html', false);

    // Berkas panduannya sendiri tetap dapat diakses langsung oleh yang tahu alamatnya.
    expect(public_path('panduan/super.html'))->toBeFile();
});

it('setiap tangkapan layar yang dirujuk panduan tersedia', function () {
    foreach (glob(public_path('panduan/*.html')) as $berkas) {
        preg_match_all('#img/([^"]+\.jpg)#', file_get_contents($berkas), $m);

        foreach ($m[1] as $gambar) {
            expect(public_path("panduan/img/{$gambar}"))->toBeFile();
        }
    }
});
