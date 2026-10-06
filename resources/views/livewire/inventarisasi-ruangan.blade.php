<div x-data="{
        memindai: false, pemindai: null, galat: null,
        async mulai() {
            this.galat = null;
            try {
                this.pemindai = new window.Html5Qrcode('pembaca-inventarisasi');
                await this.pemindai.start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, (isi) => { $wire.set('teks', isi); $wire.pindai(); });
                this.memindai = true;
            } catch (e) { this.galat = 'Kamera tidak dapat dibuka. Ketik kode secara manual atau ketuk barang pada daftar.'; this.pemindai = null; }
        },
        async berhenti() { if (this.pemindai) { try { await this.pemindai.stop(); } catch (e) {} this.pemindai = null; } this.memindai = false; },
    }">
    <h1 class="text-xl font-semibold">Inventarisasi: {{ $inv->ruangan->nama }}</h1>
    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">{{ $inv->periode->nama }} · {{ $inv->status->label() }}</p>

    <div class="mb-4" aria-label="Kemajuan">
        <div class="mb-1 flex justify-between text-sm"><span>Kemajuan</span><strong>{{ $this->progres }}%</strong></div>
        <div class="h-2 w-full overflow-hidden rounded bg-gray-200 dark:bg-gray-800"><div class="h-2 bg-blue-600" style="width: {{ $this->progres }}%"></div></div>
    </div>

    @if ($pesan) <p class="mb-3 rounded bg-green-50 p-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200" role="status">{{ $pesan }}</p> @endif
    @if ($peringatan) <p class="mb-3 rounded bg-amber-50 p-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100" role="alert">{{ $peringatan }}</p> @endif

    @if (! $this->sudahSelesai())
        <div class="mb-3 overflow-hidden rounded-lg border border-gray-300 dark:border-gray-700" x-show="memindai" x-cloak><div id="pembaca-inventarisasi" wire:ignore class="w-full"></div></div>
        <p class="mb-3 text-sm text-red-600" x-show="galat" x-text="galat" x-cloak></p>
        <div class="mb-3 flex gap-2">
            <button type="button" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white" x-show="!memindai" x-on:click="mulai()">Buka kamera</button>
            <button type="button" class="rounded-lg border border-gray-400 px-4 py-2 font-medium" x-show="memindai" x-cloak x-on:click="berhenti()">Tutup kamera</button>
        </div>
        <form wire:submit="pindai" class="mb-5 flex gap-2">
            <input type="text" wire:model="teks" placeholder="Isi QR / kode label (baru atau lama)" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900" autocomplete="off">
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 font-medium text-white dark:bg-gray-200 dark:text-gray-900">Cari</button>
        </form>
    @endif

    <h2 class="mb-2 font-medium">Barang di ruangan ({{ $this->daftarAset->count() }})</h2>
    <ul class="mb-6 divide-y rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
        @forelse ($this->daftarAset as $a)
            @php($h = $this->hasilPerAset->get($a->id))
            <li class="p-3 text-sm {{ $terakhirId === $a->id ? 'bg-blue-50 dark:bg-blue-950' : '' }}">
                <div class="flex items-start justify-between gap-2">
                    <span>
                        <strong>{{ $a->nama }}</strong> <span class="text-gray-500">{{ $a->kode_tampil }}</span><br>
                        <span class="text-gray-500">Kondisi data: {{ $a->kondisi->label() }}</span>
                        @if ($a->label_perlu_cetak_ulang)
                            <br><span class="text-amber-700">⚠ Label perlu dicetak ulang dan ditempel</span>
                        @endif
                    </span>
                    <span class="shrink-0 text-right">
                        @if ($h)
                            <span class="rounded-full border px-2 py-0.5 text-xs {{ $h->hasil === \App\Enums\HasilInventarisasi::TidakDitemukan ? 'border-red-400 text-red-700' : 'border-green-500 text-green-700' }}">{{ $h->hasil->label() }}@if ($h->kondisi_ditemukan) → {{ $h->kondisi_ditemukan->value }} @endif</span>
                        @else
                            <span class="rounded-full border px-2 py-0.5 text-xs text-gray-500">Belum dipindai</span>
                        @endif
                    </span>
                </div>
                @if (! $this->sudahSelesai())
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button" wire:click="tandaiDitemukan('{{ $a->id }}')" class="rounded border border-gray-400 px-2 py-1 text-xs">Ditemukan</button>
                        @foreach (\App\Enums\KondisiAset::cases() as $k)
                            @if ($k !== $a->kondisi)
                                <button type="button" wire:click="koreksiKondisi('{{ $a->id }}', '{{ $k->value }}')" class="rounded border border-gray-300 px-2 py-1 text-xs">Ditemukan, kondisi {{ $k->value }}</button>
                            @endif
                        @endforeach
                    </div>
                @endif
            </li>
        @empty
            <li class="p-3 text-sm text-gray-500">Tidak ada barang tercatat di ruangan ini.</li>
        @endforelse
    </ul>

    <h2 class="mb-2 font-medium">Temuan berlebih ({{ $this->temuanBerlebih->count() }})</h2>
    @foreach ($this->temuanBerlebih as $t) <p class="mb-1 text-sm">• {{ $t->deskripsi_temuan }}</p> @endforeach
    @if (! $this->sudahSelesai())
        <form wire:submit="catatTemuanBerlebih" class="mb-6 mt-2 space-y-2">
            <textarea wire:model="deskripsiTemuan" rows="2" placeholder="Barang yang ada di ruangan tetapi tidak tercatat" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900"></textarea>
            @error('deskripsiTemuan') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <input type="url" wire:model="fotoTemuan" placeholder="Tautan foto Google Drive (opsional)" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
            @error('fotoTemuan') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <button type="submit" class="rounded-lg border border-gray-400 px-4 py-2 text-sm font-medium">Catat temuan berlebih</button>
        </form>

        <div class="rounded-lg border border-gray-300 p-3 dark:border-gray-700">
            @if (! $konfirmasiSelesai)
                <button type="button" wire:click="$set('konfirmasiSelesai', true)" class="rounded-lg bg-green-700 px-4 py-2 font-medium text-white">Selesai ruangan</button>
            @else
                <p class="mb-2 text-sm">Barang yang belum dipindai akan dicatat <strong>tidak ditemukan</strong>. Lanjutkan?</p>
                <button type="button" wire:click="selesai" class="mr-2 rounded-lg bg-green-700 px-4 py-2 font-medium text-white">Ya, selesai</button>
                <button type="button" wire:click="$set('konfirmasiSelesai', false)" class="rounded-lg border px-4 py-2">Batal</button>
            @endif
        </div>
    @endif
</div>
