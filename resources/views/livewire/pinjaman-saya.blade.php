<div>
    <h1 class="mb-1 text-xl font-semibold">Pinjaman saya</h1>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Riwayat pengajuan dan peminjaman Anda. <a class="underline" href="{{ route('pinjam') }}">Ajukan peminjaman baru</a>.</p>

    <ul class="space-y-3">
        @forelse ($this->daftar as $p)
            <li class="rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <strong>{{ $p->nomor }}</strong>
                        <span class="ml-1 rounded-full border px-2 py-0.5 text-xs">{{ $p->status->label() }}</span>
                        @if ($p->terlambat()) <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">Terlambat</span> @endif
                    </div>
                    @if (in_array($p->status, [\App\Enums\StatusPeminjaman::Diajukan, \App\Enums\StatusPeminjaman::Disetujui], true))
                        <button type="button" wire:click="batalkan('{{ $p->id }}')" wire:confirm="Batalkan peminjaman ini?" class="text-red-600">Batalkan</button>
                    @endif
                </div>
                <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $p->mulai->translatedFormat('d F Y H:i') }} – {{ $p->rencana_kembali->translatedFormat('d F Y H:i') }}</p>
                <p class="mt-1">{{ $p->item->map(fn ($i) => $i->aset->nama)->implode(', ') }}</p>
                @if ($p->catatan_keputusan) <p class="mt-1 text-gray-600 dark:text-gray-400">Catatan: {{ $p->catatan_keputusan }}</p> @endif
            </li>
        @empty
            <li class="rounded-xl border border-dashed p-6 text-center text-sm text-gray-500">Belum ada peminjaman.</li>
        @endforelse
    </ul>
</div>
