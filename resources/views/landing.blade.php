<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIMAN FKIP — Sistem Informasi Manajemen Aset</title>
    <style>
        body{font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;margin:0;background:#f8fafc;color:#0f172a}
        header{background:#0f172a;color:#fff;padding:48px 20px 40px;text-align:center}
        header h1{margin:0 0 8px;font-size:2rem} header p{margin:0 auto;max-width:640px;color:#cbd5e1}
        .masuk{display:inline-block;margin-top:22px;background:#2563eb;color:#fff;padding:10px 26px;border-radius:8px;text-decoration:none;font-weight:600}
        main{max-width:1000px;margin:0 auto;padding:32px 20px 56px}
        h2{font-size:1.2rem;margin:0 0 14px}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
        .kartu{display:block;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;text-decoration:none;color:inherit}
        .kartu:hover{border-color:#2563eb;box-shadow:0 2px 8px rgba(37,99,235,.12)}
        .kartu strong{display:block;font-size:1.05rem;margin-bottom:2px} .kartu span{color:#475569;font-size:.92rem}
        .info{margin-top:36px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px;color:#334155;font-size:.95rem}
        footer{text-align:center;color:#64748b;font-size:.85rem;padding:0 20px 36px}
    </style>
</head>
<body>
<header>
    <h1>SIMAN FKIP</h1>
    <p>Sistem Informasi Manajemen Aset dan Barang Milik Negara Fakultas Keguruan dan Ilmu Pendidikan Universitas Siliwangi —
        pencatatan barang per kode + NUP, label QR, peminjaman, pemeliharaan, inventarisasi, dan laporan.</p>
    <a class="masuk" href="{{ url('admin/login') }}">Masuk</a>
</header>
<main>
    <h2>Panduan pengguna per peran</h2>
    <div class="grid">
        @foreach ([
            'umum' => ['Semua pengguna', 'Masuk, pindai QR barang, dan lapor kerusakan tanpa login.'],
            'civitas' => ['Civitas (dosen dan ormawa)', 'Mengajukan dan memantau peminjaman barang.'],
            'pic' => ['PIC Ruangan', 'Pindai, ubah kondisi, pinjamkan, DBR, inventarisasi, pemeliharaan.'],
            'admin' => ['Admin BMN', 'Aset, mutasi, inventarisasi, penghapusan, dan laporan.'],
            'pejabat' => ['Pejabat Penatausahaan', 'Mengesahkan DBR, berita acara, dan usulan penghapusan.'],
            'pimpinan' => ['Pimpinan', 'Dasbor dan laporan (tanpa data pribadi).'],
        ] as $peran => [$nama, $ket])
            <a class="kartu" href="{{ url("panduan/{$peran}.html") }}"><strong>{{ $nama }}</strong><span>{{ $ket }}</span></a>
        @endforeach
    </div>
    <div class="info">
        Pindai QR pada label barang untuk melihat informasi publik dan melaporkan kerusakan. Akun dibuat oleh admin BMN
        (surel <code>@unsil.ac.id</code>); admin dan super-admin wajib memakai autentikasi dua langkah.
    </div>
</main>
<footer>SIMAN FKIP v{{ config('app.version') }} · Universitas Siliwangi</footer>
</body>
</html>
