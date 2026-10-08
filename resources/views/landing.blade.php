<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ASET FKIP — Sistem Informasi Manajemen Aset &amp; BMN Universitas Siliwangi</title>
    <meta name="description" content="ASET FKIP: pencatatan barang milik negara per kode + NUP, label QR, peminjaman, pemeliharaan, inventarisasi, dan laporan di FKIP Universitas Siliwangi.">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">

    <nav class="absolute inset-x-0 top-0 z-10">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5 text-white">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5 font-bold tracking-tight">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-blue-600 text-sm shadow-lg shadow-blue-900/40">A</span>
                ASET FKIP
            </a>
            <div class="flex items-center gap-5 text-sm font-medium">
                <a href="#fitur" class="hidden text-slate-300 hover:text-white sm:inline">Fitur</a>
                <a href="#alur" class="hidden text-slate-300 hover:text-white sm:inline">Alur</a>
                <a href="#panduan" class="hidden text-slate-300 hover:text-white sm:inline">Panduan</a>
                <a href="{{ url('admin/login') }}" class="rounded-lg bg-white/10 px-4 py-2 ring-1 ring-white/25 transition hover:bg-white/20">Masuk</a>
            </div>
        </div>
    </nav>

    <header class="relative overflow-hidden bg-slate-900 text-white">
        <div class="absolute inset-0 bg-gradient-to-br from-blue-800/50 via-slate-900 to-slate-900"></div>
        <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-6 pb-20 pt-32 lg:grid-cols-2 lg:pb-28 lg:pt-40">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-blue-200 ring-1 ring-white/20">
                    FKIP Universitas Siliwangi
                </span>
                <h1 class="mt-6 text-4xl font-bold tracking-tight sm:text-5xl">
                    Aset fakultas, <span class="text-blue-300">tercatat rapi</span> dan mudah dilacak.
                </h1>
                <p class="mt-5 max-w-xl text-balance text-lg text-slate-300">
                    Sistem informasi manajemen aset dan Barang Milik Negara FKIP: dari pencatatan per kode + NUP,
                    label QR, peminjaman, hingga inventarisasi dan laporan untuk pimpinan.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="{{ url('admin/login') }}"
                       class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-900/30 transition hover:bg-blue-500">
                        Masuk ke sistem
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 0 1 1.414 0l4 4a1 1 0 0 1 0 1.414l-4 4a1 1 0 0 1-1.414-1.414L12.586 9H3a1 1 0 1 1 0-2h9.586l-2.293-2.293a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/></svg>
                    </a>
                    <a href="#panduan" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-6 py-3 text-sm font-semibold text-white ring-1 ring-white/20 transition hover:bg-white/20">
                        Lihat panduan pengguna
                    </a>
                </div>
                <dl class="mt-12 grid max-w-md grid-cols-3 gap-6 border-t border-white/10 pt-6">
                    <div><dt class="text-xs text-slate-400">Peran pengguna</dt><dd class="mt-1 text-2xl font-bold">6</dd></div>
                    <div><dt class="text-xs text-slate-400">Pelacakan</dt><dd class="mt-1 text-2xl font-bold">QR</dd></div>
                    <div><dt class="text-xs text-slate-400">Identitas barang</dt><dd class="mt-1 text-2xl font-bold">Kode+NUP</dd></div>
                </dl>
            </div>

            {{-- Ilustrasi kartu aset --}}
            <div class="relative mx-auto w-full max-w-sm" aria-hidden="true">
                <div class="rounded-2xl bg-white p-6 text-slate-900 shadow-2xl shadow-blue-950/50 ring-1 ring-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Label aset</p>
                            <p class="mt-1 font-semibold">Proyektor LCD</p>
                            <p class="text-sm text-slate-500">Ruang Lab. Komputer</p>
                        </div>
                        <svg class="h-20 w-20 shrink-0 text-slate-900" viewBox="0 0 21 21" fill="currentColor">
                            <path d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2zM9 0h2v2H9zM9 3h1v3H9zM11 4h2v2h-2zM8 8h2v2H8zM11 8h3v1h-3zM15 9h2v2h-2zM18 8h3v2h-3zM9 11h3v2H9zM13 12h2v3h-2zM16 13h2v2h-2zM19 12h2v2h-2zM8 15h2v3H8zM11 16h2v2h-2zM14 17h3v2h-3zM18 16h3v1h-3zM10 19h3v2h-3zM15 20h2v1h-2zM19 18h2v3h-2z"/>
                        </svg>
                    </div>
                    <dl class="mt-5 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 text-sm">
                        <div><dt class="text-slate-400">Kode barang</dt><dd class="font-mono font-medium">3.05.02.01</dd></div>
                        <div><dt class="text-slate-400">NUP</dt><dd class="font-mono font-medium">0007</dd></div>
                        <div><dt class="text-slate-400">Kondisi</dt><dd><span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Baik</span></dd></div>
                        <div><dt class="text-slate-400">Status</dt><dd><span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">Tersedia</span></dd></div>
                    </dl>
                </div>
                <div class="absolute -bottom-5 -left-5 rounded-xl bg-slate-800 px-4 py-3 text-xs text-slate-200 shadow-xl ring-1 ring-white/10">
                    <span class="font-semibold text-white">Pindai QR</span> → info publik &amp; lapor kerusakan
                </div>
            </div>
        </div>
    </header>

    <main>
        <section id="fitur" class="mx-auto max-w-6xl px-6 py-20">
            <div class="max-w-2xl">
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Semua siklus hidup aset dalam satu sistem</h2>
                <p class="mt-3 text-slate-600">Menggantikan lembar kerja manual dengan data yang terpusat, terotorisasi per peran, dan teraudit.</p>
            </div>
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['Pencatatan kode + NUP', 'Setiap unit barang punya identitas unik sesuai tata kelola BMN, lengkap dengan foto dan kondisi.', 0],
                    ['Label QR', 'Cetak dan cetak ulang label otomatis. Siapa pun dapat memindai untuk melihat informasi publik.', 1],
                    ['Peminjaman & mutasi', 'Civitas mengajukan peminjaman; perpindahan antar-ruangan tercatat beserta pelakunya.', 2],
                    ['Pemeliharaan & laporan kerusakan', 'Laporan dari pemindaian langsung diteruskan ke PIC ruangan untuk ditindaklanjuti.', 3],
                    ['Inventarisasi & DBR', 'Pencacahan berkala per ruangan, Daftar Barang Ruangan, dan berita acara yang dapat disahkan.', 4],
                    ['Dasbor & audit', 'Pimpinan memantau kondisi aset tanpa data pribadi; setiap perubahan penting tercatat.', 5],
                ] as [$judul, $ket, $ikon])
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-blue-50 text-blue-600">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                @switch($ikon)
                                @case(0)<path d="M4 7h16M4 12h16M4 17h10"/>@break
                                @case(1)<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h2v2h-2zM18 18h2v2h-2zM14 18h2M18 14h2"/>@break
                                @case(2)<path d="M7 7h11l-3-3M17 17H6l3 3"/>@break
                                @case(3)<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.6-.6-.6-2.6z"/>@break
                                @case(4)<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>@break
                                @case(5)<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>@break
                                @endswitch
                            </svg>
                        </span>
                        <h3 class="mt-4 font-semibold text-slate-900">{{ $judul }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ $ket }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section id="alur" class="bg-white py-20">
            <div class="mx-auto max-w-6xl px-6">
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Cara kerjanya</h2>
                <ol class="mt-10 grid gap-8 md:grid-cols-4">
                    @foreach ([
                        ['Catat', 'Admin BMN mendaftarkan barang per kode + NUP ke ruangan.'],
                        ['Labeli', 'Label QR dicetak dan ditempel pada barang.'],
                        ['Gunakan', 'Civitas meminjam; PIC mengelola kondisi dan mutasi.'],
                        ['Pantau', 'Inventarisasi, DBR, dan laporan disahkan dan ditinjau pimpinan.'],
                    ] as $i => [$judul, $ket])
                        <li class="relative">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-blue-600 text-sm font-bold text-white">{{ $i + 1 }}</span>
                            <h3 class="mt-4 font-semibold">{{ $judul }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $ket }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section id="panduan" class="mx-auto max-w-6xl px-6 py-20">
            <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Panduan pengguna per peran</h2>
            <p class="mt-3 text-slate-600">Pilih peran Anda untuk melihat panduan bergambar langkah demi langkah.</p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'umum' => ['Semua pengguna', 'Masuk, pindai QR barang, dan lapor kerusakan tanpa login.'],
                    'civitas' => ['Civitas (dosen dan ormawa)', 'Mengajukan dan memantau peminjaman barang.'],
                    'pic' => ['PIC Ruangan', 'Pindai, ubah kondisi, pinjamkan, DBR, inventarisasi, pemeliharaan.'],
                    'admin' => ['Admin BMN', 'Aset, mutasi, inventarisasi, penghapusan, dan laporan.'],
                    'pejabat' => ['Pejabat Penatausahaan', 'Mengesahkan DBR, berita acara, dan usulan penghapusan.'],
                    'pimpinan' => ['Pimpinan', 'Dasbor dan laporan (tanpa data pribadi).'],
                ] as $peran => [$nama, $ket])
                    <a href="{{ url("panduan/{$peran}.html") }}"
                       class="group block rounded-xl border border-slate-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-blue-400 hover:shadow-md">
                        <strong class="block text-slate-900 group-hover:text-blue-700">{{ $nama }} <span aria-hidden="true" class="inline-block transition group-hover:translate-x-1">→</span></strong>
                        <span class="mt-1 block text-sm text-slate-600">{{ $ket }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-6 pb-20">
            <div class="flex flex-col items-start justify-between gap-6 rounded-2xl bg-slate-900 p-8 text-white sm:flex-row sm:items-center">
                <div class="max-w-xl">
                    <h2 class="text-xl font-semibold">Akun dibuat oleh admin BMN</h2>
                    <p class="mt-2 text-sm text-slate-300">
                        Gunakan surel <code class="rounded bg-white/10 px-1.5 py-0.5">@unsil.ac.id</code>. Admin dan super-admin
                        wajib memakai autentikasi dua langkah.
                    </p>
                </div>
                <a href="{{ url('admin/login') }}" class="shrink-0 rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold transition hover:bg-blue-500">Masuk</a>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white py-8 text-center text-sm text-slate-500">
        ASET FKIP v{{ config('app.version') }} · FKIP Universitas Siliwangi
    </footer>
</body>
</html>
