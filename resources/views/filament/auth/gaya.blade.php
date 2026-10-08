{{-- Selaraskan halaman auth Filament (masuk, reset sandi, MFA) dengan tema landing page. --}}
<style>
    .fi-simple-layout {
        background:
            radial-gradient(60rem 30rem at 85% -10%, rgb(59 130 246 / .28), transparent 60%),
            linear-gradient(135deg, #1e3a8a66 0%, #0f172a 45%, #0f172a 100%) #0f172a;
        min-height: 100vh;
    }
    .fi-simple-main {
        border-radius: 1rem;
        box-shadow: 0 25px 50px -12px rgb(2 6 23 / .6);
    }
    .fi-simple-layout .fi-logo { font-weight: 700; letter-spacing: -.01em; }
    .aset-auth-lencana {
        display: flex; justify-content: center; margin-bottom: .5rem;
    }
    .aset-auth-lencana span {
        font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase;
        padding: .3rem .8rem; border-radius: 9999px; color: #1d4ed8; background: #eff6ff;
    }
    .aset-auth-kembali { text-align: center; margin-top: 1.25rem; font-size: .875rem; }
    .aset-auth-kembali a { color: #bfdbfe; text-decoration: none; }
    .aset-auth-kembali a:hover { color: #fff; text-decoration: underline; }
    .fi-simple-layout > .aset-auth-kembali { position: absolute; left: 0; right: 0; bottom: 1.25rem; margin: 0; }
</style>
