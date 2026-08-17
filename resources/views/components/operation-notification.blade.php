@if(session('success') || $errors->any())
@php($notificationIsError = $errors->any())
<div
    x-data="{
        open: true,
        init() {
            @if(!$notificationIsError)
            setTimeout(() => this.open = false, 5000);
            @endif
        }
    }"
    x-show="open"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @keydown.escape.window="open = false"
    @click.self="open = false"
    class="operation-notification-overlay fixed inset-0 z-[100] flex items-center justify-center p-4"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="operation-notification-title"
    style="display: none;"
>
    <section
        x-show="open"
        x-transition:enter="transition ease-out duration-500"
        x-transition:enter-start="opacity-0 scale-75 translate-y-8"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="operation-notification-card {{ $notificationIsError ? 'is-error' : 'is-success' }} relative w-full max-w-md overflow-hidden rounded-[1.75rem] text-center"
    >
        <div class="operation-notification-glow" aria-hidden="true"></div>

        <button type="button" @click="open = false" class="operation-notification-close absolute left-5 top-5 z-10 flex h-9 w-9 items-center justify-center rounded-full" aria-label="داخستن">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>

        <div class="relative px-7 pb-7 pt-9 sm:px-10">
            <div class="operation-notification-icon mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full" aria-hidden="true">
                @if($notificationIsError)
                    <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path class="operation-notification-mark" stroke-linecap="round" stroke-width="2.4" d="M12 7v6"/>
                        <path class="operation-notification-dot" stroke-linecap="round" stroke-width="2.8" d="M12 17h.01"/>
                    </svg>
                @else
                    <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path class="operation-notification-check" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="m6.5 12.5 3.5 3.5 7.5-8"/>
                    </svg>
                @endif
            </div>

            <p class="mb-2 text-xs font-bold tracking-[0.14em] {{ $notificationIsError ? 'text-rose-600' : 'text-emerald-600' }}">ئاگادارکردنەوەی سیستەم</p>
            <h2 id="operation-notification-title" class="text-2xl font-black text-slate-900">
                {{ $notificationIsError ? 'هەڵەیەک ڕوویدا' : 'کردارەکە سەرکەوتوو بوو' }}
            </h2>

            <div class="mt-4 text-sm leading-7 text-slate-600">
                @if($notificationIsError)
                    <ul class="space-y-1.5 text-right">
                        @foreach($errors->all() as $error)
                            <li class="flex items-start gap-2 rounded-xl bg-rose-50 px-3 py-2 text-rose-700">
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-500"></span>
                                <span>{{ $error }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p>{{ session('success') }}</p>
                @endif
            </div>

            <button type="button" @click="open = false" class="mt-7 w-full rounded-xl px-5 py-3 font-bold text-white transition duration-200 hover:-translate-y-0.5 {{ $notificationIsError ? 'bg-rose-600 shadow-lg shadow-rose-200 hover:bg-rose-700' : 'bg-emerald-600 shadow-lg shadow-emerald-200 hover:bg-emerald-700' }}">
                باشە
            </button>
        </div>

        @unless($notificationIsError)
            <div class="operation-notification-progress absolute inset-x-0 bottom-0 h-1 bg-emerald-500"></div>
        @endunless
    </section>
</div>
@endif
