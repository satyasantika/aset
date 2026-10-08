<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Pindai QR' }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    @if (app(\STS\FilamentImpersonate\ImpersonateManager::class)->isImpersonating())
        <div class="flex items-center justify-center gap-5 bg-gray-800 px-4 py-3 text-sm text-gray-100">
            <span>Anda sedang berpura-pura menjadi <strong>{{ auth()->user()?->name }}</strong></span>
            <a href="{{ route('filament-impersonate.leave') }}" class="rounded bg-white/10 px-3 py-1 font-semibold hover:bg-white/20">Tinggalkan</a>
        </div>
    @endif
    <main class="mx-auto max-w-xl px-4 pb-12 pt-5">
        {{ $slot }}
    </main>
</body>
</html>
