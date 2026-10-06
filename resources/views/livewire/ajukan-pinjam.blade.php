<div>
    <h1 class="mb-1 text-xl font-semibold">Pinjam barang</h1>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Pilih barang dan rentang waktu. Pengajuan diputuskan oleh PIC ruangan; statusnya dapat dilihat di <a class="underline" href="{{ route('pinjaman-saya') }}">Pinjaman saya</a>.</p>

    @if ($nomorBerhasil)
        <div class="mb-4 rounded-lg border border-green-300 bg-green-50 p-4 text-green-900 dark:border-green-700 dark:bg-green-950 dark:text-green-100" role="status">
            Pengajuan <strong>{{ $nomorBerhasil }}</strong> terkirim dan menunggu persetujuan.
        </div>
    @endif

    <form wire:submit="ajukan" class="space-y-6">
        <section>
            <h2 class="mb-2 font-medium">1. Rentang waktu</h2>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-sm">Mulai<input type="datetime-local" wire:model.live="mulai" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900"></label>
                <label class="text-sm">Rencana kembali<input type="datetime-local" wire:model.live="selesai" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900"></label>
            </div>
            @error('mulai') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('rencana_kembali') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>

        <section>
            <h2 class="mb-2 font-medium">2. Pilih barang</h2>
            <div class="mb-2 grid grid-cols-2 gap-2">
                <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari barang…" class="rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                <select wire:model.live="ruanganId" class="rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                    <option value="">Semua ruangan</option>
                    @foreach (\App\Models\Ruangan::query()->orderBy('nama')->get(['id', 'nama']) as $r)
                        <option value="{{ $r->id }}">{{ $r->nama }}</option>
                    @endforeach
                </select>
            </div>
            <ul class="divide-y rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                @forelse ($this->katalog as $a)
                    @php($alasan = $this->alasanTidakTersedia($a))
                    <li class="flex items-start gap-3 p-3 text-sm {{ $alasan ? 'opacity-60' : '' }}">
                        <input type="checkbox" value="{{ $a->id }}" wire:model="dipilih" class="mt-1" @disabled($alasan !== null)>
                        <span>
                            <strong>{{ $a->nama }}</strong>
                            <span class="text-gray-500">{{ $a->merk_tipe }}</span><br>
                            <span class="text-gray-500">{{ $this->kategori($a) ?: 'Tanpa kategori' }} · {{ $a->ruangan?->nama }} · {{ $a->kondisi->label() }}</span>
                            @if ($alasan) <br><span class="text-red-600">Tidak tersedia pada rentang ini</span> @endif
                        </span>
                    </li>
                @empty
                    <li class="p-3 text-sm text-gray-500">Tidak ada barang yang dapat dipinjam.</li>
                @endforelse
            </ul>
            @error('dipilih') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('aset') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @foreach ($errors->get('aset.*') as $galat) @foreach ($galat as $g) <p class="mt-1 text-sm text-red-600">{{ $g }}</p> @endforeach @endforeach
        </section>

        <section>
            <h2 class="mb-2 font-medium">3. Keperluan</h2>
            <textarea wire:model="keperluan" rows="2" placeholder="Keperluan peminjaman" class="mb-2 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900"></textarea>
            @error('keperluan') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <input type="text" wire:model="unit" placeholder="Unit / prodi / ormawa (opsional)" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
        </section>

        <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2.5 font-medium text-white">Ajukan peminjaman</button>
    </form>

    @if ($this->ruanganKatalog->isNotEmpty())
        <section class="mt-10">
            <h2 class="mb-2 font-medium">Ruangan yang dapat dipakai</h2>
            <p class="mb-2 text-xs text-gray-500">Pemakaian ruangan untuk kegiatan diajukan melalui sistem Surat.</p>
            <ul class="rounded-lg border border-gray-200 text-sm dark:border-gray-800">
                @foreach ($this->ruanganKatalog as $r)
                    <li class="border-b p-2 last:border-b-0 dark:border-gray-800">{{ $r->nama }} <span class="text-gray-500">{{ $r->gedung?->nama }}{{ $r->kapasitas ? ' · kapasitas '.$r->kapasitas : '' }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
