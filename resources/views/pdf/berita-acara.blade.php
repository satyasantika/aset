<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Inventarisasi — {{ $periode->nama }}</title>
    <style>
        @page { margin: 14mm 14mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #000; }
        .kop { text-align: center; line-height: 1.25; border-bottom: 1.2pt solid #000; padding-bottom: 3mm; margin-bottom: 4mm; }
        .kop .b1 { font-size: 9pt; } .kop .b2 { font-size: 12pt; font-weight: bold; } .kop .b3 { font-size: 10.5pt; font-weight: bold; } .kop .kecil { font-size: 7.5pt; }
        h1 { text-align: center; font-size: 12pt; margin: 2mm 0 1mm; text-transform: uppercase; }
        h2 { font-size: 10pt; margin: 5mm 0 1.5mm; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
        table.data th, table.data td { border: 0.4pt solid #000; padding: 1.1mm 1.5mm; vertical-align: top; }
        table.data th { background: #e8e8e8; text-align: center; }
        td.c { text-align: center; }
        table.ttd { width: 100%; margin-top: 9mm; border-collapse: collapse; page-break-inside: avoid; }
        table.ttd td { width: 50%; text-align: center; vertical-align: top; padding: 0 6mm; }
        .ruang-ttd { height: 18mm; }
        .meta { font-size: 7.5pt; color: #333; margin-top: 3mm; }
    </style>
</head>
<body>
<div class="kop">
    <div class="b1">{{ $s['kop']['instansi_baris1'] ?? '' }}</div>
    <div class="b2">{{ $s['kop']['instansi_baris2'] ?? '' }}</div>
    <div class="b3">{{ $s['kop']['nama_unit'] ?? '' }}</div>
    <div class="kecil">{{ $s['kop']['alamat'] ?? '' }}</div>
</div>

<h1>Berita Acara Hasil Inventarisasi Barang Milik Negara</h1>
<p style="text-align:center">{{ $s['periode']['nama'] }} · {{ $s['periode']['jenis'] === 'sensus' ? 'Sensus barang' : 'Opname internal' }}<br>
    Periode {{ \Carbon\Carbon::parse($s['periode']['mulai'])->translatedFormat('d F Y') }}
    @if ($s['periode']['selesai_rencana']) s.d. {{ \Carbon\Carbon::parse($s['periode']['selesai_rencana'])->translatedFormat('d F Y') }} @endif</p>

<h2>A. Rekapitulasi per ruangan</h2>
<table class="data">
    <thead><tr><th>No</th><th>Ruangan</th><th>Petugas</th><th>Barang tercatat</th><th>Ditemukan</th><th>Kondisi berubah</th><th>Tidak ditemukan</th><th>Berlebih</th></tr></thead>
    <tbody>
    @foreach ($s['ruangan'] as $i => $r)
        <tr><td class="c">{{ $i + 1 }}</td><td>{{ $r['nama'] }} ({{ $r['kode'] }})</td><td>{{ implode(', ', $r['petugas']) ?: '-' }}</td>
            <td class="c">{{ $r['total'] }}</td><td class="c">{{ $r['ditemukan'] }}</td><td class="c">{{ $r['kondisi_berubah'] }}</td><td class="c">{{ $r['tidak_ditemukan'] }}</td><td class="c">{{ $r['berlebih'] }}</td></tr>
    @endforeach
        <tr><th colspan="3">Jumlah</th><th>{{ $s['total']['total'] }}</th><th>{{ $s['total']['ditemukan'] }}</th><th>{{ $s['total']['kondisi_berubah'] }}</th><th>{{ $s['total']['tidak_ditemukan'] }}</th><th>{{ $s['total']['berlebih'] }}</th></tr>
    </tbody>
</table>

<h2>B. Barang tidak ditemukan</h2>
<table class="data">
    <thead><tr><th>No</th><th>Ruangan</th><th>Kode Barang</th><th>NUP</th><th>Nama Barang</th><th>Merk/Tipe</th></tr></thead>
    <tbody>
    @forelse ($s['tidak_ditemukan'] as $i => $b)
        <tr><td class="c">{{ $i + 1 }}</td><td>{{ $b['ruangan'] }}</td><td>{{ $b['kode_barang'] ?? $b['kode_internal'] }}</td><td class="c">{{ $b['nup'] ?? '-' }}</td><td>{{ $b['nama'] }}</td><td>{{ $b['merk_tipe'] }}</td></tr>
    @empty
        <tr><td colspan="6" class="c">Tidak ada.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>C. Barang dengan kondisi berubah</h2>
<table class="data">
    <thead><tr><th>No</th><th>Ruangan</th><th>Kode Barang</th><th>NUP</th><th>Nama Barang</th><th>Kondisi Data</th><th>Kondisi Ditemukan</th></tr></thead>
    <tbody>
    @forelse ($s['kondisi_berubah'] as $i => $b)
        <tr><td class="c">{{ $i + 1 }}</td><td>{{ $b['ruangan'] }}</td><td>{{ $b['kode_barang'] ?? $b['kode_internal'] }}</td><td class="c">{{ $b['nup'] ?? '-' }}</td><td>{{ $b['nama'] }}</td><td class="c">{{ $b['kondisi_data'] }}</td><td class="c">{{ $b['kondisi_ditemukan'] }}</td></tr>
    @empty
        <tr><td colspan="7" class="c">Tidak ada.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>D. Temuan berlebih (belum tercatat)</h2>
<table class="data">
    <thead><tr><th>No</th><th>Ruangan</th><th>Deskripsi</th></tr></thead>
    <tbody>
    @forelse ($s['berlebih'] as $i => $b)
        <tr><td class="c">{{ $i + 1 }}</td><td>{{ $b['ruangan'] }}</td><td>{{ $b['deskripsi'] }}</td></tr>
    @empty
        <tr><td colspan="3" class="c">Tidak ada.</td></tr>
    @endforelse
    </tbody>
</table>

@php($pejabat = $s['penandatangan']['pejabat'] ?? null)
@php($penutup = $s['penandatangan']['penutup'] ?? null)
<table class="ttd">
    <tr>
        <td>Dibuat oleh,<br><div class="ruang-ttd"></div><strong><u>{{ $penutup['nama'] ?? '........................' }}</u></strong><br>@if (! empty($penutup['nip'])) NIP. {{ $penutup['nip'] }} @endif</td>
        <td>{{ $s['kop']['kota_surat'] ?? '' }}@if ($pejabat), {{ \Carbon\Carbon::parse($pejabat['pada'])->timezone(config('app.timezone'))->translatedFormat('d F Y') }}@endif<br>
            {{ $pejabat['jabatan'] ?? 'Pejabat Penatausahaan' }},<br><div class="ruang-ttd"></div>
            <strong><u>{{ $pejabat['nama'] ?? '........................' }}</u></strong><br>@if (! empty($pejabat['nip'])) NIP. {{ $pejabat['nip'] }} @endif</td>
    </tr>
</table>
<div class="meta">Dokumen dihasilkan dari snapshot penutupan periode pada {{ \Carbon\Carbon::parse($s['periode']['ditutup_pada'])->timezone(config('app.timezone'))->translatedFormat('d F Y H:i') }}.</div>
</body>
</html>
