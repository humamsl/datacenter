<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Anti-cache di mobile (penyebab 419 Page Expired) --}}
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Login &middot; {{ $AppCfg['app_name'] }}</title>
    @if($AppCfg['favicon_url'])
        <link rel="icon" href="{{ $AppCfg['favicon_url'] }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .login-hero {
            @if($AppCfg['login_bg'])
                background-image: linear-gradient(135deg, rgb(0 23 24 / 40%), rgb(15 23 42 / 80%)),
                                  url('{{ asset('storage/'.$AppCfg['login_bg']) }}');
                background-size: cover;
                background-position: center;
            @else
                background:
                    radial-gradient(circle at 20% 20%, rgba(20,184,166,0.30), transparent 50%),
                    radial-gradient(circle at 80% 30%, rgba(16,185,129,0.20), transparent 55%),
                    radial-gradient(circle at 60% 100%, rgba(56,189,248,0.14), transparent 60%),
                    linear-gradient(135deg, #0f172a 0%, #062275 68%, #0d1e94 100%);
            @endif
            color: white;
        }
    </style>
</head>
<body class="h-full bg-slate-50">
<div class="min-h-screen grid lg:grid-cols-5">

    <!-- KIRI: hero -->
    <div class="flex lg:col-span-3 flex-col justify-between p-12 login-hero relative overflow-hidden" style="min-height: 440px;">
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-white/5 blur-3xl"></div>
        <div class="absolute -bottom-32 -left-12 w-96 h-96 rounded-full bg-white/5 blur-3xl"></div>

        <div class="flex items-center justify-between gap-3 relative z-10">
            <div class="flex items-center gap-3">
                @if($AppCfg['logo_url'])
                    <img src="{{ $AppCfg['logo_url'] }}" alt="" class="w-12 h-12 object-contain bg-white/95 rounded-xl p-1.5 shadow-soft">
                @else
                    <div class="w-12 h-12 rounded-xl bg-white/20 grid place-items-center text-white font-bold shadow-soft ring-2 ring-white/40">
                        {{ mb_substr($AppCfg['app_name'], 0, 1) }}
                    </div>
                @endif
            </div>
            <a href="../" class="text-xs font-semibold text-white/85 hover:text-white bg-white/10 hover:bg-white/15 border border-white/20 rounded-full px-3.5 py-2 transition">
                ← Beranda
            </a>
        </div>

        <div class="max-w-xl relative z-10">
            <h1 class="text-4xl xl:text-5xl font-bold leading-tight">{!! $AppCfg['login_title'] !!}</h1>
            <p class="mt-4 text-white/85 text-lg leading-relaxed">{{ $AppCfg['login_subtitle'] }}</p>
        </div>

        <div class="text-xs text-white/70 relative z-10">
            © {{ date('Y') }} {{ $AppCfg['app_name'] }} — Powered by AnonymouSL
        </div>
    </div>

    <!-- KANAN: form -->
    <div class="lg:col-span-2 flex items-center justify-center p-6 sm:p-12 bg-slate-50">
        <div class="w-full max-w-md">
            <div>
                <h2 class="text-3xl font-bold text-ink-900">Login Data Center</h2>
                <p class="text-sm text-ink-500 mt-1.5">Khusus untuk Admin sekolah.</p>

                @if(session('error'))
                    <div class="mt-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.post') }}" class="mt-8 space-y-5" id="login-form">
                    <input type="hidden" name="_token" id="csrf-token-input" value="{{ csrf_token() }}">

                    <div>
                        <label class="label">Email</label>
                        <input type="text" name="username" value="{{ old('username') }}"
                               class="input" autofocus required
                               placeholder="Masukkan email Anda">
                        @error('username')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="label">Password</label>
                        <div class="relative" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'" name="password" class="input pr-10" required placeholder="••••••••">
                            <button type="button" @click="show=!show" class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-500 hover:text-ink-700">
                                <span x-show="!show" x-cloak>👁️‍🗨️</span>
                                <span x-show="show" x-cloak>👁️‍🗨️</span>
                            </button>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-ink-600">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Ingat saya di perangkat ini
                    </label>

                    <button type="submit" class="btn-primary w-full text-base py-3 group">
                        Login
                        <span class="transition group-hover:translate-x-1">→</span>
                    </button>
                </form>

                <div class="mt-8 text-center text-xs text-ink-500">
                    Lupa password? Hubungi administrator sistem.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('login-form');
        const tokenInput = document.getElementById('csrf-token-input');
        if (! form || ! tokenInput) return;

        let submitting = false;
        form.addEventListener('submit', async (e) => {
            if (submitting) return;
            e.preventDefault();
            submitting = true;
            try {
                const res = await fetch("{{ route('csrf.refresh') }}", {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data && data.token) tokenInput.value = data.token;
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta && data && data.token) meta.setAttribute('content', data.token);
                }
            } catch (err) {
                // network gagal — tetap submit dengan token yang ada
            }
            form.submit();
        });
    });
</script>
</body>
</html>
