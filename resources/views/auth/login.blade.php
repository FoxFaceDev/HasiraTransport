<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>چوونە ژوورەوە</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased min-h-screen bg-slate-50 text-slate-900">
    <main class="relative min-h-screen flex items-center justify-center overflow-hidden p-5">
        <div class="absolute -top-36 -right-28 w-[30rem] h-[30rem] rounded-full bg-blue-100/70 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-24 w-[28rem] h-[28rem] rounded-full bg-cyan-100/60 blur-3xl"></div>

        <div class="relative w-full max-w-md bg-white border border-slate-200 rounded-[1.75rem] shadow-[0_24px_70px_rgba(30,64,110,0.12)] p-8 sm:p-10">
            <div class="text-center mb-8">
                <div class="brand-mark w-16 h-16 flex items-center justify-center mx-auto mb-5 text-white">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h11v9H3V7Zm11 3h3.5l3 3v3H14v-6ZM6.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" />
                    </svg>
                </div>
                <div class="text-xs font-bold uppercase tracking-[0.2em] text-blue-600 mb-2">Hasira Transport</div>
                <h1 class="text-3xl font-bold text-slate-900">بەخێربێیتەوە</h1>
                <p class="text-slate-500 mt-2">بۆ بەردەوامبوون بچۆرە ژوورەوە</p>
            </div>

            @if($errors->any())
            <div class="bg-rose-50 border border-rose-200 text-rose-700 p-4 rounded-xl mb-6">
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="form-label block mb-2" for="email">ئیمەیڵ</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="glass-input w-full px-4 py-3 rounded-xl" autocomplete="email" required autofocus>
                </div>
                <div>
                    <label class="form-label block mb-2" for="password">وشەی نهێنی</label>
                    <input id="password" type="password" name="password" class="glass-input w-full px-4 py-3 rounded-xl" autocomplete="current-password" required>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer w-fit">
                    <input id="remember" type="checkbox" name="remember" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span>لەبیرم مەکە</span>
                </label>
                <button type="submit" class="btn-primary w-full py-3.5 rounded-xl mt-2 font-bold text-base">
                    چوونە ژوورەوە
                </button>
            </form>
        </div>
    </main>
</body>
</html>
