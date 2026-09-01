@extends('layouts.layout')

@section('content')
<div class="glass-panel p-6" x-data="gatekeeperQueueManager({{ Js::from($snapshot) }}, { statusFilter: {{ Js::from($status) }} })" @keydown.escape.window="closeScheduleModal()">
    <div class="mb-6 flex flex-col gap-5">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <div class="mb-1 text-xs font-bold text-blue-600">پاڵاوتنی لیست</div>
                <h2 class="text-2xl font-bold text-slate-900">
                    @if($status === 'green') لیستی هاتووەکان @endif
                    @if($status === 'red') لیستی نەهاتووەکان @endif
                    @if($status === 'yellow') لیستی دواخراوەکان @endif
                </h2>
            </div>
            <a href="{{ route('gatekeeper.index') }}" class="btn-secondary rounded-lg px-4 py-2 font-semibold">گەڕانەوە</a>
        </div>
        <div class="flex flex-col justify-between gap-4 border-t border-slate-200 pt-4 xl:flex-row xl:items-center">
            <input type="search" x-model="search" @input.debounce.100ms="search = $event.target.value" placeholder="گەڕان بەدوای بارهەڵگر، خاوەنی خەت، مۆبایل، VIN..." dir="rtl" autocomplete="off" class="glass-input w-full rounded-lg px-4 py-2 text-right text-sm xl:w-72">
            <div class="min-w-0">@include('gatekeeper.partials.sync-status')</div>
        </div>
    </div>
    @include('gatekeeper.partials.offline-table')
</div>
@endsection
