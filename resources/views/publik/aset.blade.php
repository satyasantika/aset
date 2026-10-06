@extends('publik.layout')

@section('judul', $aset->nama)

@section('isi')
    <div class="kartu">
        <h1>{{ $aset->nama }}</h1>
        <dl>
            <dt>Merk / tipe</dt><dd>{{ $aset->merk_tipe ?: '—' }}</dd>
            <dt>Kategori</dt><dd>{{ $kategori ?: '—' }}</dd>
            <dt>Ruangan</dt><dd>{{ $aset->ruangan?->nama ?? ($aset->lokasi_lainnya ?: '—') }}</dd>
            <dt>Kondisi</dt><dd><span class="lencana">{{ $aset->kondisi->label() }}</span></dd>
            <dt>Tahun perolehan</dt><dd>{{ $aset->tahun_perolehan ?? '—' }}</dd>
            <dt>Kode barang / NUP</dt><dd>{{ $aset->kode_tampil ?: '—' }}</dd>
        </dl>
        <a class="tombol" href="{{ url('/lapor-kerusakan/'.$aset->id) }}" rel="nofollow">Laporkan kerusakan</a>
    </div>
@endsection
