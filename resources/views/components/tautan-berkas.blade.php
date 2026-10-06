<div class="tautan-berkas" data-jenis="{{ $tautan->jenis }}">
    @if ($urlFoto)
        <img src="{{ $urlFoto }}" alt="{{ $tautan->label }}" loading="lazy" referrerpolicy="no-referrer"
             style="max-width:100%;height:auto"
             onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
        <div class="tautan-berkas-placeholder" style="display:none">Foto tidak dapat dimuat.</div>
    @endif

    @if ($urlPratinjau)
        <iframe src="{{ $urlPratinjau }}" title="{{ $tautan->label }}" loading="lazy" sandbox="allow-scripts allow-same-origin"
                style="width:100%;height:480px;border:0"></iframe>
    @endif

    <a href="{{ $tautan->url }}" target="_blank" rel="noopener noreferrer">Buka berkas: {{ $tautan->label }}</a>

    @if ($mati)
        <span class="tautan-berkas-mati">(tautan tidak dapat diakses)</span>
    @endif
</div>
