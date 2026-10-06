<?php

it('menampilkan landing page dengan tautan ke panduan semua peran', function () {
    $res = $this->get('/')->assertOk()->assertSee('SIMAN FKIP')->assertSee('Panduan pengguna per peran');

    foreach (['umum', 'civitas', 'pic', 'admin', 'pejabat', 'pimpinan', 'super'] as $peran) {
        $res->assertSee("panduan/{$peran}.html", false);
        expect(public_path("panduan/{$peran}.html"))->toBeFile();
    }
});

it('setiap tangkapan layar yang dirujuk panduan tersedia', function () {
    foreach (glob(public_path('panduan/*.html')) as $berkas) {
        preg_match_all('#img/([^"]+\.jpg)#', file_get_contents($berkas), $m);

        foreach ($m[1] as $gambar) {
            expect(public_path("panduan/img/{$gambar}"))->toBeFile();
        }
    }
});
