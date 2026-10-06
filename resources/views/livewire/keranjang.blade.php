<div>
    <h1 class="mb-1 text-xl font-semibold">Keranjang peminjaman</h1>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Catat peminjaman langsung. Semua aset harus tersedia; bila ada yang tidak tersedia, seluruh keranjang gagal.</p>

    @if ($nomorBerhasil)
        <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-4 text-green-900 dark:border-green-700 dark:bg-green-950 dark:text-green-100" role="status">
            Peminjaman <strong>{{ $nomorBerhasil }}</strong> tercatat dan berstatus dipinjam.
        </div>
    @endif

    <section class="mb-6">
        <h2 class="mb-2 font-medium">1. Aset</h2>
        <form wire:submit="tambahDariTeks" class="mb-2 flex gap-2">
            <input type="text" wire:model="teksPindai" placeholder="Tempel/ketik isi QR atau kode label"
                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900" autocomplete="off">
            <button type="submit" class="rounded-lg bg-gray-800 px-3 py-2 text-white dark:bg-gray-200 dark:text-gray-900">Tambah</button>
        </form>
        <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Atau cari nama / merk / kode / NUP"
               class="mb-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
        @if ($this->hasilCari->isNotEmpty())
            <ul class="mb-3 divide-y rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                @foreach ($this->hasilCari as $a)
                    <li class="flex items-center justify-between gap-2 p-2 text-sm">
                        <span>{{ $a->nama }} <span class="text-gray-500">— {{ $a->kode_tampil }} · {{ $a->ruangan?->nama }}</span></span>
                        <button type="button" wire:click="tambah('{{ $a->id }}')" class="rounded border px-2 py-1">Tambah</button>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($pesan) <p class="mb-2 text-sm text-amber-700" role="alert">{{ $pesan }}</p> @endif

        <ul class="rounded-lg border border-gray-200 dark:border-gray-800">
            @forelse ($this->isiKeranjang as $a)
                @php($alasan = $this->alasanTidakTersedia($a))
                <li class="flex items-start justify-between gap-2 border-b p-2 text-sm last:border-b-0 dark:border-gray-800">
                    <span>
                        <strong>{{ $a->nama }}</strong> <span class="text-gray-500">— {{ $a->kode_tampil }} · {{ $a->ruangan?->nama }}</span>
                        @if ($alasan) <br><span class="text-red-600">{{ $alasan }}</span> @endif
                    </span>
                    <button type="button" wire:click="hapus('{{ $a->id }}')" class="text-red-600">Hapus</button>
                </li>
            @empty
                <li class="p-3 text-sm text-gray-500">Keranjang kosong.</li>
            @endforelse
        </ul>
        @error('aset') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @foreach ($errors->get('aset.*') as $galat) @foreach ($galat as $g) <p class="mt-1 text-sm text-red-600">{{ $g }}</p> @endforeach @endforeach
    </section>

    <form wire:submit="catat" class="space-y-6">
        <section>
            <h2 class="mb-2 font-medium">2. Peminjam</h2>
            <div class="mb-2 flex gap-4 text-sm">
                <label><input type="radio" wire:model.live="modePeminjam" value="akun"> Akun civitas</label>
                <label><input type="radio" wire:model.live="modePeminjam" value="manual"> Nama manual</label>
            </div>
            @if ($modePeminjam === 'akun')
                <input type="search" wire:model.live.debounce.300ms="cariPeminjam" placeholder="Cari nama / surel civitas"
                       class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                @foreach ($this->hasilPeminjam as $u)
                    <button type="button" wire:click="pilihPeminjam('{{ $u->id }}')" class="mt-1 block w-full rounded border px-3 py-1.5 text-left text-sm">{{ $u->name }} <span class="text-gray-500">{{ $u->email }}</span></button>
                @endforeach
                @error('peminjamUserId') <p class="mt-1 text-sm text-red-600">Pilih peminjam dari daftar.</p> @enderror
            @else
                <input type="text" wire:model="nama" placeholder="Nama peminjam" class="mb-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                @error('nama') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @endif
            <div class="mt-2 grid grid-cols-2 gap-2">
                <input type="text" wire:model="unit" placeholder="Unit / prodi / ormawa" class="rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                <input type="text" wire:model="kontak" placeholder="Kontak (HP)" class="rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
            </div>
        </section>

        <section>
            <h2 class="mb-2 font-medium">3. Keperluan & rencana kembali</h2>
            <textarea wire:model="keperluan" rows="2" placeholder="Keperluan" class="mb-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900"></textarea>
            @error('keperluan') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex gap-2">
                <input type="date" wire:model="kembaliTanggal" class="rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                <input type="time" wire:model="kembaliJam" class="rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
            </div>
            <p class="mt-1 text-xs text-gray-500">Maksimal {{ $this->maksHari() }} hari dari sekarang.</p>
            @error('kembaliTanggal') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('rencana_kembali') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </section>

        <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 font-medium text-white" @disabled(count($daftar) === 0)>Catat peminjaman</button>
    </form>
</div>
