<?php

return [
    // Lama (detik) menunggu lock per aset sebelum menyerah (peminjaman/mutasi).
    'lock_tunggu_detik' => (int) env('ASET_LOCK_TUNGGU_DETIK', 5),

    // Pengelompokan DKPS LAMDIK (docs/FORMAT-DKPS.md). Awalan kode barang BMN untuk Peralatan Komputer (3.10.02).
    'dkps' => [
        'awalan_kode_tik' => env('ASET_DKPS_AWALAN_TIK', '31002'),
    ],
];
