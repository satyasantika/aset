<x-filament-widgets::widget>
    @if ($peringatan)
        <x-filament::section>
            <div role="alert" class="flex items-start gap-3 {{ $peringatan['tingkat'] === 'danger' ? 'text-danger-600 dark:text-danger-400' : 'text-warning-600 dark:text-warning-400' }}">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-6 w-6 shrink-0" />
                <div>
                    <p class="font-semibold">Pengingat inventarisasi (PMK 181/2016 Pasal 19)</p>
                    <p class="text-sm">{{ $peringatan['pesan'] }}</p>
                    <a class="text-sm underline" href="{{ \App\Filament\Resources\Inventarisasi\PeriodeInventarisasiResource::getUrl('index') }}">Kelola inventarisasi</a>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
