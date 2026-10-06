<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label aset</title>
    <style>
        @page { margin: 8mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7pt; margin: 0; }
        table.kisi { border-collapse: separate; border-spacing: 3mm; width: 100%; }
        td.label { width: 33%; height: 34mm; border: 0.3mm solid #000; padding: 1.5mm; vertical-align: top; }
        table.isi { width: 100%; border-collapse: collapse; }
        td.qr { width: 24mm; vertical-align: top; }
        td.qr img { width: 23mm; height: 23mm; }
        .kop { font-weight: bold; font-size: 5.5pt; text-align: center; border-bottom: 0.2mm solid #000; padding-bottom: 0.6mm; margin-bottom: 1mm; }
        .nama { font-weight: bold; font-size: 7.5pt; }
        .kode { font-weight: bold; font-size: 7pt; }
        .kecil { font-size: 6pt; color: #222; }
    </style>
</head>
<body>
<table class="kisi">
    @foreach ($stiker->chunk(3) as $baris)
        <tr>
            @foreach ($baris as $s)
                @php($a = $s['aset'])
                <td class="label">
                    <div class="kop">INVENTARIS FKIP UNIVERSITAS SILIWANGI</div>
                    <table class="isi"><tr>
                        <td class="qr"><img src="{{ $s['qr'] }}" alt="QR"></td>
                        <td>
                            <div class="nama">{{ \Illuminate\Support\Str::limit($a->nama, 34) }}</div>
                            @if ($a->merk_tipe)<div class="kecil">{{ \Illuminate\Support\Str::limit($a->merk_tipe, 30) }}</div>@endif
                            <div class="kode">{{ $a->kode_tampil }}</div>
                            <div class="kecil">Tahun: {{ $a->tahun_perolehan ?? '—' }}</div>
                            <div class="kecil">Ruang: {{ $a->ruangan?->kode ?? ($a->lokasi_lainnya ?? '—') }}</div>
                        </td>
                    </tr></table>
                </td>
            @endforeach
            @for ($i = $baris->count(); $i < 3; $i++)<td></td>@endfor
        </tr>
    @endforeach
</table>
</body>
</html>
