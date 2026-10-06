<x-filament-widgets::widget>
    <x-filament::section heading="Aset per ruangan" description="Jumlah dan nilai perolehan; kolom B / RR / RB = kondisi.">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left"><th class="py-1 pr-3">Ruangan</th><th class="px-2 text-right">Jumlah</th><th class="px-2 text-right">B</th><th class="px-2 text-right">RR</th><th class="px-2 text-right">RB</th><th class="pl-2 text-right">Nilai (Rp)</th></tr></thead>
                <tbody>
                @forelse (array_slice($s['per_ruangan'], 0, 30) as $r)
                    <tr class="border-t border-gray-200 dark:border-white/10"><td class="py-1 pr-3">{{ $r['nama'] }} <span class="text-gray-500">({{ $r['kode'] }})</span></td><td class="px-2 text-right">{{ $r['jumlah'] }}</td><td class="px-2 text-right">{{ $r['B'] }}</td><td class="px-2 text-right">{{ $r['RR'] }}</td><td class="px-2 text-right">{{ $r['RB'] }}</td><td class="pl-2 text-right">{{ number_format((float) $r['nilai'], 0, ',', '.') }}</td></tr>
                @empty
                    <tr><td colspan="6" class="py-2 text-gray-500">Belum ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
            @if (count($s['per_ruangan']) > 30) <p class="mt-1 text-xs text-gray-500">+ {{ count($s['per_ruangan']) - 30 }} ruangan lainnya.</p> @endif
        </div>
    </x-filament::section>
    <x-filament::section heading="Aset per kategori" class="mt-4">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left"><th class="py-1 pr-3">Kategori</th><th class="px-2 text-right">Jumlah</th><th class="pl-2 text-right">Nilai (Rp)</th></tr></thead>
                <tbody>
                @foreach ($s['per_kategori'] as $k)
                    <tr class="border-t border-gray-200 dark:border-white/10"><td class="py-1 pr-3">{{ $k['kategori'] }}</td><td class="px-2 text-right">{{ $k['jumlah'] }}</td><td class="pl-2 text-right">{{ number_format((float) $k['nilai'], 0, ',', '.') }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-gray-500">Dihitung {{ \Carbon\Carbon::parse($s['dihitung_pada'])->timezone(config('app.timezone'))->translatedFormat('d F Y H:i') }} (cache {{ \App\Support\Dasbor::TTL_MENIT }} menit, diperbarui saat data berubah).</p>
    </x-filament::section>
</x-filament-widgets::widget>
