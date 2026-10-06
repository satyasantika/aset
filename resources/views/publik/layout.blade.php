<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('judul', 'Inventaris FKIP') — {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; --bg:#f6f7f9; --kartu:#fff; --teks:#1b2430; --redup:#5b6676; --garis:#dde2e8; --aksen:#1d4ed8; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0f141a; --kartu:#171d26; --teks:#e6e9ee; --redup:#9aa6b6; --garis:#2a3342; --aksen:#7aa2ff; } }
        body { margin:0; background:var(--bg); color:var(--teks); font:16px/1.5 system-ui, sans-serif; }
        main { max-width:34rem; margin:0 auto; padding:1.25rem 1rem 3rem; }
        .kartu { background:var(--kartu); border:1px solid var(--garis); border-radius:.75rem; padding:1.25rem; }
        h1 { font-size:1.25rem; margin:.25rem 0 1rem; }
        dl { display:grid; grid-template-columns:9rem 1fr; gap:.5rem 1rem; margin:0; }
        dt { color:var(--redup); } dd { margin:0; font-weight:600; word-break:break-word; }
        .kop { color:var(--redup); font-size:.8rem; letter-spacing:.04em; text-transform:uppercase; }
        .tombol { display:inline-block; margin-top:1.25rem; padding:.7rem 1rem; border-radius:.5rem; background:var(--aksen); color:#fff; text-decoration:none; font-weight:600; }
        .lencana { display:inline-block; padding:.1rem .6rem; border-radius:999px; font-size:.85rem; border:1px solid var(--garis); }
    </style>
</head>
<body>
<main>
    <p class="kop">Inventaris FKIP Universitas Siliwangi</p>
    @yield('isi')
</main>
</body>
</html>
