@extends('layouts.layout')

@section('content')
<div class="glass-panel p-6" x-data="gatekeeperQueueManager({{ Js::from($snapshot) }}, { exportBaseUrl: {{ Js::from(route('gatekeeper.export')) }} })" @keydown.escape.window="closeActionsModal(); closeScheduleModal()">
    <div class="mb-6 flex flex-col gap-5">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <div class="mb-1 text-xs font-bold text-blue-600">بەڕێوەبردنی هاتوچۆ</div>
                <h2 class="text-2xl font-bold text-slate-900">کۆنترۆڵی دەروازە</h2>
                <p class="mt-1 text-gray-400">گۆڕانکارییەکان ڕاستەوخۆ لە سێرڤەر هەڵدەگیرێن.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @include('gatekeeper.partials.export-control')
                @can('reset queue')
                <form action="{{ route('gatekeeper.reset') }}" method="POST" @submit.prevent="resetQueue($el)">
                    @csrf
                    <button type="submit" :disabled="isResetting || !online" class="flex items-center gap-2 whitespace-nowrap rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 font-semibold text-rose-700 transition-colors hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50">
                        <svg class="h-5 w-5" :class="isResetting ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8 8 0 0 0 4.582 9M20 20v-5h-.581m0 0A8 8 0 0 1 4.582 13M15 15h4.419"/></svg>
                        <span x-text="isResetting ? 'ڕاپۆرت دروست دەکرێت...' : 'سفرکردنەوە'">سفرکردنەوە</span>
                    </button>
                </form>
                @endcan
            </div>
        </div>
        <div class="flex flex-col gap-4 border-t border-slate-200 pt-4 md:flex-row md:items-end">
            <input type="search" x-model="search" @input.debounce.100ms="search = $event.target.value" placeholder="گەڕان بەدوای بارهەڵگر، خاوەنی خەت، مۆبایل، VIN..." dir="rtl" autocomplete="off" class="glass-input w-full rounded-lg px-4 py-2 text-right text-sm xl:w-72">
            @include('gatekeeper.partials.sort-control')
        </div>
    </div>
    @include('gatekeeper.partials.queue-table')
</div>
@endsection
