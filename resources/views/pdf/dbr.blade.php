<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $dbr->judul() }}</title>
    <style>
        @page { margin: 12mm 12mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #000; }
        .kop { text-align: center; line-height: 1.25; border-bottom: 1.2pt solid #000; padding-bottom: 3mm; margin-bottom: 4mm; }
        .kop .b1 { font-size: 9pt; } .kop .b2 { font-size: 12pt; font-weight: bold; } .kop .b3 { font-size: 10.5pt; font-weight: bold; } .kop .kecil { font-size: 7.5pt; }
        h1 { text-align: center; font-size: 11.5pt; margin: 2mm 0 1mm; text-transform: uppercase; }
        .sub { text-align: center; margin-bottom: 3mm; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 0.4pt solid #000; padding: 1.2mm 1.6mm; vertical-align: top; }
        table.data th { background: #e8e8e8; text-align: center; }
        td.c { text-align: center; }
        table.ttd { width: 100%; margin-top: 8mm; border-collapse: collapse; page-break-inside: avoid; }
        table.ttd td { width: 50%; text-align: center; vertical-align: top; padding: 0 6mm; }
        .ruang-ttd { height: 18mm; }
        .tanda-air { position: fixed; top: 38%; left: 18%; font-size: 90pt; color: #d9d9d9; transform: rotate(-20deg); z-index: -1; }
        .meta { font-size: 7.5pt; color: #333; margin-top: 3mm; }
    </style>
</head>
<body>
@if ($draf) <div class="tanda-air">DRAF</div> @endif

<div class="kop">
    <div class="b1">{{ $s['kop']['instansi_baris1'] ?? '' }}</div>
    <div class="b2">{{ $s['kop']['instansi_baris2'] ?? '' }}</div>
    <div class="b3">{{ $s['kop']['nama_unit'] ?? '' }}</div>
    <div class="kecil">{{ $s['kop']['alamat'] ?? '' }}</div>
    <div class="kecil">{{ $s['kop']['kontak'] ?? '' }}</div>
</div>

<h1>{{ $dbr->adalahDbl() ? 'Daftar Barang Lainnya (DBL)' : 'Daftar Barang Ruangan (DBR)' }}</h1>
<div class="sub">
    @if (! $dbr->adalahDbl())
        Ruangan: <strong>{{ $s['ruangan']['nama'] ?? '-' }}</strong> ({{ $s['ruangan']['kode'] ?? '-' }})@if (! empty($s['ruangan']['gedung'])) — {{ $s['ruangan']['gedung'] }}@endif<br>
    @endif
    Versi {{ $dbr->versi }} · Status: {{ $dbr->status->pernahDisahkan() ? 'Disahkan' : $dbr->status->label() }} · Jumlah barang: {{ $s['ringkasan']['jumlah'] ?? count($s['aset']) }}
    (B: {{ $s['ringkasan']['B'] ?? 0 }}, RR: {{ $s['ringkasan']['RR'] ?? 0 }}, RB: {{ $s['ringkasan']['RB'] ?? 0 }})
</div>

<table class="data">
    <thead>
    <tr>
        <th style="width:6mm">No</th><th style="width:24mm">Kode Barang</th><th style="width:12mm">NUP</th><th>Nama Barang</th>
        <th style="width:34mm">Merk/Tipe</th><th style="width:12mm">Tahun</th><th style="width:12mm">Kondisi</th><th style="width:38mm">Keterangan</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($s['aset'] as $i => $a)
        <tr>
            <td class="c">{{ $i + 1 }}</td>
            <td>{{ $a['kode_barang'] ?? ($a['kode_internal'] ?? '-') }}</td>
            <td class="c">{{ $a['nup'] ?? '-' }}</td>
            <td>{{ $a['nama'] }}@if (! empty($a['lokasi_lainnya'])) <br><small>Lokasi: {{ $a['lokasi_lainnya'] }}</small>@endif</td>
            <td>{{ $a['merk_tipe'] ?? '' }}</td>
            <td class="c">{{ $a['tahun_perolehan'] ?? '-' }}</td>
            <td class="c">{{ $a['kondisi'] }}</td>
            <td>{{ $a['keterangan'] ?? '' }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="c">Tidak ada barang.</td></tr>
    @endforelse
    </tbody>
</table>

@php($pic = $s['penandatangan']['pic'] ?? null)
@php($pejabat = $s['penandatangan']['pejabat'] ?? null)
<table class="ttd">
    <tr>
        <td>
            Penanggung jawab ruangan,<br>
            <div class="ruang-ttd"></div>
            <strong><u>{{ $pic['nama'] ?? '................................' }}</u></strong><br>
            @if (! empty($pic['nip'])) NIP. {{ $pic['nip'] }} @endif
            @if ($pic) <div class="meta">Disetujui {{ \Carbon\Carbon::parse($pic['pada'])->timezone(config('app.timezone'))->translatedFormat('d F Y H:i') }}</div> @endif
        </td>
        <td>
            {{ $s['kop']['kota_surat'] ?? '' }}@if ($pejabat), {{ \Carbon\Carbon::parse($pejabat['pada'])->timezone(config('app.timezone'))->translatedFormat('d F Y') }}@endif<br>
            {{ $pejabat['jabatan'] ?? 'Pejabat Penatausahaan' }},<br>
            <div class="ruang-ttd"></div>
            <strong><u>{{ $pejabat['nama'] ?? '................................' }}</u></strong><br>
            @if (! empty($pejabat['nip'])) NIP. {{ $pejabat['nip'] }} @endif
        </td>
    </tr>
</table>
<div class="meta">Dokumen ini dihasilkan dari snapshot versi {{ $dbr->versi }} pada {{ \Carbon\Carbon::parse($s['dibangkitkan_pada'])->timezone(config('app.timezone'))->translatedFormat('d F Y H:i') }}.</div>
</body>
</html>
