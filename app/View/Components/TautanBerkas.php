<?php

namespace App\View\Components;

use App\Enums\StatusCekTautan;
use App\Models\TautanBerkas as ModelTautan;
use App\Support\TautanBerkasParser;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** `<x-tautan-berkas :tautan="$tautan" />` — tombol "Buka berkas", foto Drive, dan pratinjau PDF (§1a.5). */
class TautanBerkas extends Component
{
    public ?string $urlFoto = null;

    public ?string $urlPratinjau = null;

    public bool $mati;

    public function __construct(public ModelTautan $tautan, public bool $pratinjau = false)
    {
        $this->mati = $tautan->status_cek === StatusCekTautan::TidakDapatDiakses;

        if ($tautan->jenis === 'foto') {
            $this->urlFoto = TautanBerkasParser::urlFoto($tautan->url);
        } elseif ($pratinjau) {
            $this->urlPratinjau = TautanBerkasParser::urlPratinjau($tautan->url);
        }
    }

    public function render(): View
    {
        return view('components.tautan-berkas');
    }
}
