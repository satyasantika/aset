<div x-data="{
        memindai: false, pemindai: null, galat: null,
        async mulai() {
            this.galat = null;
            try {
                this.pemindai = new window.Html5Qrcode('pembaca-qr');
                await this.pemindai.start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, (isi) => {
                    this.berhenti();
                    $wire.set('teks', isi);
                    $wire.cari();
                });
                this.memindai = true;
            } catch (e) {
                this.galat = 'Kamera tidak dapat dibuka. Izinkan akses kamera atau ketik kode secara manual.';
                this.pemindai = null;
            }
        },
        async berhenti() {
            if (this.pemindai) { try { await this.pemindai.stop(); } catch (e) {} this.pemindai = null; }
            this.memindai = false;
        },
    }" x-on:livewire:navigating.window="berhenti()">
    <h1 class="mb-1 text-xl font-semibold">Pindai QR aset</h1>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Arahkan kamera ke label, atau ketik kode label (baru maupun lama).</p>

    <div class="mb-3 overflow-hidden rounded-lg border border-gray-300 dark:border-gray-700" x-show="memindai" x-cloak>
        <div id="pembaca-qr" wire:ignore class="w-full"></div>
    </div>
    <p class="mb-3 text-sm text-red-600" x-show="galat" x-text="galat" x-cloak></p>

    <div class="mb-4 flex gap-2">
        <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white" x-show="!memindai" x-on:click="mulai()">Buka kamera</button>
        <button type="button" class="rounded-lg border border-gray-400 px-4 py-2 font-medium" x-show="memindai" x-cloak x-on:click="berhenti()">Tutup kamera</button>
    </div>

    <form wire:submit="cari" class="mb-6 flex gap-2">
        <input type="text" wire:model="teks" placeholder="mis. MBL-935464-1 atau tempel URL label"
               class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900" autocomplete="off">
        <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 font-medium text-white dark:bg-gray-200 dark:text-gray-900">Cari</button>
    </form>
    @error('teks') <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror

    @if ($pesan)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100" role="alert">
            {!! $pesan !!}
        </div>
    @endif

    @if ($this->aset)
        @php($aset = $this->aset)
        @php($staf = auth()->user()->can('aset.lihat'))
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">{{ $aset->nama }}</h2>
            <dl class="mt-3 grid grid-cols-[8.5rem_1fr] gap-x-3 gap-y-1 text-sm">
                <dt class="text-gray-500">Merk / tipe</dt><dd class="font-medium">{{ $aset->merk_tipe ?: '—' }}</dd>
                <dt class="text-gray-500">Kategori</dt><dd class="font-medium">{{ $this->kategori ?: '—' }}</dd>
                <dt class="text-gray-500">Ruangan</dt><dd class="font-medium">{{ $aset->ruangan?->nama ?? ($aset->lokasi_lainnya ?: '—') }}</dd>
                <dt class="text-gray-500">Kondisi</dt><dd class="font-medium">{{ $aset->kondisi->label() }}</dd>
                <dt class="text-gray-500">Tahun</dt><dd class="font-medium">{{ $aset->tahun_perolehan ?? '—' }}</dd>
                <dt class="text-gray-500">Kode / NUP</dt><dd class="font-medium">{{ $aset->kode_tampil ?: '—' }}</dd>
                @if ($staf)
                    <dt class="text-gray-500">Status</dt><dd class="font-medium">{{ $aset->status->label() }}</dd>
                @endif
            </dl>

            @if ($kondisiBaru)
                <p class="mt-3 rounded bg-green-50 p-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200" role="status">Kondisi diperbarui menjadi {{ $aset->kondisi->label() }}.</p>
            @endif

            @if ($nomorMutasi)
                <p class="mt-3 rounded bg-green-50 p-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200" role="status">Mutasi {{ $nomorMutasi }} diajukan dan menunggu persetujuan admin BMN.</p>
            @endif

            @if ($aset->ruangan && auth()->user()->can('ajukan', [\App\Models\Mutasi::class, $aset->ruangan]) && $aset->status === \App\Enums\StatusAset::Aktif)
                <details class="mt-4 rounded-lg border border-gray-300 p-3 dark:border-gray-700">
                    <summary class="cursor-pointer text-sm font-medium">Ajukan mutasi lokasi</summary>
                    <form wire:submit="ajukanMutasi" class="mt-3 space-y-2">
                        <select wire:model="tujuanMutasi" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                            <option value="">Pilih ruangan tujuan…</option>
                            @foreach ($this->ruanganTujuan as $id => $nama)
                                <option value="{{ $id }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                        @error('tujuanMutasi') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <textarea wire:model="alasanMutasi" rows="2" placeholder="Alasan mutasi" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900"></textarea>
                        @error('alasanMutasi') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        @error('aset') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Ajukan</button>
                    </form>
                </details>
            @endif

            @if ($nomorTiket)
                <p class="mt-3 rounded bg-green-50 p-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200" role="status">Laporan diterima sebagai tiket {{ $nomorTiket }}.</p>
            @endif

            <details class="mt-4 rounded-lg border border-gray-300 p-3 dark:border-gray-700">
                <summary class="cursor-pointer text-sm font-medium">Laporkan kerusakan</summary>
                <form wire:submit="laporKerusakan" class="mt-3 space-y-2">
                    <textarea wire:model="deskripsiKerusakan" rows="3" placeholder="Apa yang rusak?" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900"></textarea>
                    @error('deskripsiKerusakan') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Kirim laporan</button>
                </form>
            </details>

            <div class="mt-4 flex flex-wrap gap-2">
                @can('ubahKondisi', $aset)
                    @foreach (\App\Enums\KondisiAset::cases() as $k)
                        @if ($k !== $aset->kondisi)
                            <button type="button" wire:click="ubahKondisi('{{ $k->value }}')" wire:confirm="Ubah kondisi menjadi {{ $k->label() }}?"
                                    class="rounded-lg border border-gray-400 px-3 py-1.5 text-sm font-medium">Jadikan {{ $k->label() }}</button>
                        @endif
                    @endforeach
                @endcan
                @if ($staf)
                    <a href="{{ url('/admin/aset/'.$aset->id) }}" class="rounded-lg border border-gray-400 px-3 py-1.5 text-sm font-medium">Buka di panel</a>
                @endif
                @if ($staf && auth()->user()->can('peminjaman.catat'))
                    <a href="{{ route('keranjang') }}" class="rounded-lg border border-gray-400 px-3 py-1.5 text-sm font-medium">Buka keranjang peminjaman</a>
                @endif

                <button type="button" wire:click="ulang" class="rounded-lg px-3 py-1.5 text-sm font-medium text-blue-700 dark:text-blue-300">Pindai lagi</button>
            </div>
        </div>
    @endif
</div>
