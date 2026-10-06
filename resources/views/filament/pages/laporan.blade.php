<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Ekspor diproses di latar belakang; tautan unduhan dikirim lewat notifikasi dan berkas dihapus otomatis dalam 24 jam.
        Data dibatasi sesuai ruangan yang Anda kelola.
    </p>
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($this->daftarLaporan() as $laporan)
            @php($aksi = $this->{'getAction'}($laporan['aksi']))
            @if ($aksi?->isVisible())
                <x-filament::section>
                    <p class="mb-3 text-sm">{{ $laporan['ket'] }}</p>
                    {{ $aksi }}
                </x-filament::section>
            @endif
        @endforeach
    </div>
    <x-filament-actions::modals />
</x-filament-panels::page>
