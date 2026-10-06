<?php

namespace App\Jobs;

use App\Enums\StatusCekTautan;
use App\Models\TautanBerkas;
use App\Notifications\TautanBerkasMati;
use App\Services\PemeriksaTautan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Memeriksa keteraksesan satu tautan berkas (antrean `tautan`); status bersifat informatif (§1a.3). */
class PeriksaTautanBerkas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly string $tautanId)
    {
        $this->onQueue('tautan');
    }

    public function handle(PemeriksaTautan $pemeriksa): void
    {
        $tautan = TautanBerkas::query()->find($this->tautanId);

        if ($tautan === null) {
            return;
        }

        $hasil = $pemeriksa->periksa($tautan->url);

        if ($hasil === null) {
            return;
        }

        $sebelumnya = $tautan->status_cek;

        $tautan->disableLogging();
        $tautan->forceFill(['status_cek' => $hasil, 'dicek_pada' => now()])->save();

        if ($hasil === StatusCekTautan::TidakDapatDiakses && $sebelumnya !== StatusCekTautan::TidakDapatDiakses) {
            $tautan->penambah?->notify(new TautanBerkasMati($tautan));
        }
    }
}
