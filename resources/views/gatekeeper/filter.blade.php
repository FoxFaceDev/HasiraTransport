@extends('layouts.layout')

@section('content')
<div class="glass-panel p-4" x-data="gatekeeperQueueManager({{ Js::from($snapshot) }}, { statusFilter: {{ Js::from($status) }}, filterDate: {{ Js::from($date) }}, sortMode: {{ Js::from($status === 'yellow' ? 'scheduled' : 'queue') }}, exportBaseUrl: {{ Js::from(route('gatekeeper.export')) }} })" @keydown.escape.window="closeActionsModal(); closeScheduleModal()">
    <div class="mb-3 flex flex-col gap-3">
        <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">
                    @if($status === 'green') لیستی هاتووەکان @endif
                    @if($status === 'red') لیستی نەهاتووەکان @endif
                    @if($status === 'yellow') لیستی دواخراوەکان @endif
                    @if($status === 'departed') لیستی ڕۆیشتووەکان @endif
                </h2>
            </div>
            <a href="{{ route('gatekeeper.index') }}" class="btn-secondary rounded-lg px-4 py-2 font-semibold">گەڕانەوە</a>
        </div>
        <div class="flex flex-col gap-3 border-t border-slate-200 pt-3 md:flex-row md:items-end">
            <label class="block flex-1 md:max-w-72">
                <span class="form-label mb-1 block">گەڕان</span>
                <input type="search" x-model="search" @input.debounce.100ms="search = $event.target.value" placeholder="بارهەڵگر، خاوەن، مۆبایل، VIN..." dir="rtl" autocomplete="off" class="glass-input w-full rounded-lg px-4 py-2 text-right text-sm">
            </label>
            @if(in_array($status, ['green', 'yellow', 'departed'], true))
            <label class="block md:w-52">
                <span class="form-label mb-1 block">فلتەر بە پێی بەروار</span>
                <input type="date" x-model="filterDate" @change="changeFilterDate($event.target.value)" class="glass-input w-full rounded-lg px-4 py-2">
            </label>
            @endif
            @include('gatekeeper.partials.sort-control')
            @include('gatekeeper.partials.export-control')
        </div>
    </div>
    @include('gatekeeper.partials.queue-table')
</div>
@endsection
