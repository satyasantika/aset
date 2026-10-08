<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('judul') — {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; --bg:#f8fafc; --kartu:#fff; --teks:#0f172a; --redup:#64748b; --garis:#e2e8f0; --aksen:#2563eb; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0b1220; --kartu:#131c2e; --teks:#e2e8f0; --redup:#94a3b8; --garis:#243044; --aksen:#60a5fa; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--bg); color:var(--teks);
               font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; padding:24px; }
        .kartu { max-width:30rem; width:100%; background:var(--kartu); border:1px solid var(--garis); border-radius:16px; padding:40px 32px; text-align:center; }
        .kode { font-size:3.5rem; font-weight:800; margin:0; color:var(--aksen); letter-spacing:-.02em; }
        h1 { font-size:1.35rem; margin:10px 0 10px; }
        p.pesan { color:var(--redup); margin:0 0 28px; font-size:.98rem; }
        .aksi { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
        .tombol { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:600; font-size:.92rem; }
        .tombol.primer { background:var(--aksen); color:#fff; }
        .tombol.sekunder { background:transparent; color:var(--teks); border:1px solid var(--garis); }
        .lencana { display:inline-block; margin-bottom:14px; font-size:.78rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--redup); }
    </style>
</head>
<body>
    <div class="kartu">
        <span class="lencana">ASET FKIP</span>
        <p class="kode">@yield('kode')</p>
        <h1>@yield('judul')</h1>
        <p class="pesan">@yield('pesan')</p>
        <div class="aksi">
            <a href="#" onclick="if (window.history.length > 1) { window.history.back(); return false; }" class="tombol sekunder">&larr; Kembali</a>
            <a href="{{ url('/') }}" class="tombol primer">Beranda</a>
        </div>
    </div>
</body>
</html>
