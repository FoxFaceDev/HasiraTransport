@extends('layouts.layout')

@section('content')
<div class="glass-panel p-4" x-data="gatekeeperQueueManager({{ Js::from($snapshot) }}, { scheduleOnly: true, dateFilter: {{ Js::from($date) }}, exportBaseUrl: {{ Js::from(route('gatekeeper.export')) }} })">
    <div class="mb-3 flex flex-col gap-3">
        <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">لیستی ئەمڕۆ</h2>
                <p class="mt-1 text-gray-400">خەتەکانی هاتن و دواخستن بۆ ڕۆژی دیاریکراو، لەگەڵ ئەو خەتانەی لە هەمان ڕۆژدا ڕۆیشتوون پیشان دەدرێن.</p>
            </div>
            <label class="block">
                <span class="form-label mb-1 block">بەروار هەڵبژێرە</span>
                <input type="date" x-model="selectedDate" @change="changeScheduleDate($event.target.value)" class="glass-input w-full rounded-lg px-4 py-2 md:w-52">
            </label>
        </div>
        <div class="flex flex-col gap-3 border-t border-slate-200 pt-3 md:flex-row md:items-end">
            <input type="search" x-model="search" @input.debounce.100ms="search = $event.target.value" placeholder="گەڕان بەدوای بارهەڵگر، خاوەنی خەت، مۆبایل، VIN..." dir="rtl" autocomplete="off" class="glass-input w-full rounded-lg px-4 py-2 text-right text-sm xl:w-72">
            @include('gatekeeper.partials.sort-control')
            @include('gatekeeper.partials.export-control')
        </div>
    </div>
    @include('gatekeeper.partials.schedule-table')
</div>
@endsection
