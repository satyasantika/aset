@extends('publik.layout')

@section('judul', 'Laporkan kerusakan')

@section('isi')
    <div class="kartu">
        <h1>Laporkan kerusakan</h1>
        <p><strong>{{ $aset->nama }}</strong>
            @if ($aset->merk_tipe) <span style="color:var(--redup)">— {{ $aset->merk_tipe }}</span> @endif
            <br><span style="color:var(--redup)">{{ $aset->ruangan?->nama ?? ($aset->lokasi_lainnya ?: 'Lokasi tidak diketahui') }} · {{ $aset->kode_tampil }}</span></p>

        @if (session('lapor_berhasil'))
            <p role="status" style="padding:.75rem 1rem;border:1px solid var(--garis);border-radius:.5rem">
                Terima kasih, laporan Anda sudah diterima.
                @if (is_string(session('lapor_berhasil')))
                    Nomor tiket: <strong>{{ session('lapor_berhasil') }}</strong>.
                @endif
                Pengelola ruangan akan menindaklanjuti.
            </p>
        @else
            @if ($errors->any())
                <ul role="alert" style="color:#b91c1c">@foreach ($errors->all() as $galat)<li>{{ $galat }}</li>@endforeach</ul>
            @endif
            <form method="POST" action="{{ route('publik.lapor.kirim', $aset->id) }}">
                @csrf
                <p><label for="deskripsi">Apa yang rusak? <span aria-hidden="true">*</span></label><br>
                    <textarea id="deskripsi" name="deskripsi" rows="4" required minlength="5" maxlength="2000" style="width:100%">{{ old('deskripsi') }}</textarea></p>
                <p><label for="nama">Nama (opsional)</label><br>
                    <input id="nama" name="nama" type="text" maxlength="150" value="{{ old('nama') }}" style="width:100%"></p>
                <p><label for="kontak">Kontak (opsional, untuk dihubungi pengelola)</label><br>
                    <input id="kontak" name="kontak" type="text" maxlength="50" value="{{ old('kontak') }}" style="width:100%"></p>
                {{-- Jebakan bot: pengguna sungguhan tidak melihat/mengisi kolom ini. --}}
                <div style="position:absolute;left:-9999px" aria-hidden="true">
                    <label for="{{ $honeypot }}">Jangan diisi</label>
                    <input id="{{ $honeypot }}" name="{{ $honeypot }}" type="text" tabindex="-1" autocomplete="off">
                </div>
                <button class="tombol" type="submit" style="border:0;cursor:pointer">Kirim laporan</button>
            </form>
            <p style="color:var(--redup);font-size:.85rem">Alamat IP Anda tidak disimpan; hanya sidik acak harian untuk mencegah penyalahgunaan.</p>
        @endif
        <p><a href="{{ url('/a/'.$aset->id) }}">← Kembali ke data barang</a></p>
    </div>
@endsection
