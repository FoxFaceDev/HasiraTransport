<div class="flex flex-wrap items-center justify-start gap-2 text-xs xl:justify-end">
    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-2 font-bold" :class="online ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'">
        <span class="h-2 w-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse'"></span>
        <span x-text="online ? 'ئۆنلاین' : 'ئۆفلاین'">ئۆنلاین</span>
    </span>
    <span x-cloak x-show="pendingCount > 0" class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-2 font-bold text-amber-700">
        <span x-text="pendingCount"></span><span>گۆڕانکاری چاوەڕێی هاوکاتکردنەوەیە</span>
    </span>
    <button type="button" @click="syncNow()" :disabled="syncing || !navigator.onLine" class="btn-secondary inline-flex items-center gap-2 rounded-xl px-3 py-2 font-bold disabled:cursor-not-allowed disabled:opacity-50">
        <svg class="h-4 w-4" :class="syncing ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8 8 0 0 0 4.582 9M20 20v-5h-.581m0 0A8 8 0 0 1 4.582 13M15 15h4.419"/></svg>
        <span x-text="syncing ? 'هاوکات دەکرێتەوە...' : 'هاوکاتکردنەوە'">هاوکاتکردنەوە</span>
    </button>
    <span class="text-slate-500">دوایین جار: <span x-text="formattedLastSync()"></span></span>
</div>
<div x-cloak x-show="syncError" x-transition class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    <span x-text="syncError"></span>
    <a x-show="authRequired" href="{{ route('login') }}" class="mr-2 font-bold underline">چوونەژوورەوە</a>
</div>
