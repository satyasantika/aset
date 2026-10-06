<?php

/*
| Kebijakan berkas (STANDAR-TEKNIS §1a): sistem tidak menerima unggahan; hanya menyimpan tautan + metadata.
*/

return [
    // `tautan` = tautan eksternal (aktif). Implementasi DiskLokal/S3 dapat ditambahkan kelak tanpa mengubah modul.
    'mode' => env('BERKAS_MODE', 'tautan'),

    // Domain yang diizinkan (cocokkan persis, atau "*.domain" untuk subdomain).
    'domain_diizinkan' => [
        'drive.google.com',
        'docs.google.com',
        '*.unsil.ac.id',
        'onedrive.live.com',
        '*.sharepoint.com',
    ],

    // Domain pemendek URL yang ditolak.
    'pemendek_url' => ['bit.ly', 'tinyurl.com', 's.id', 't.co', 'goo.gl', 'is.gd', 'ow.ly', 'rebrand.ly', 'cutt.ly', 'shorturl.at'],

    'panjang_url_maks' => 2048,

    'pemeriksaan' => [
        'timeout_detik' => 10,
        'maks_redirect' => 3,
    ],

    'jenis' => ['sk', 'bukti', 'foto', 'sertifikat', 'dokumen', 'berita_acara'],
];
