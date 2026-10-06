<?php

return [
    // Lama (detik) menunggu lock per aset sebelum menyerah (peminjaman/mutasi).
    'lock_tunggu_detik' => (int) env('ASET_LOCK_TUNGGU_DETIK', 5),
];
