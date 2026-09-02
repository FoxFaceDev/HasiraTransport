@extends('layouts.layout')

@section('content')
<div class="glass-panel p-6" x-data="gatekeeperQueueManager({{ Js::from($snapshot) }}, { scheduleOnly: true, dateFilter: {{ Js::from($date) }} })">
    <div class="mb-6 flex flex-col gap-5">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <div class="mb-1 text-xs font-bold text-blue-600">خشتەی هاتوچۆ</div>
                <h2 class="text-2xl font-bold text-slate-900">لیستی ئەمڕۆ</h2>
                <p class="mt-1 text-gray-400">تەنها خەتەکانی هاتن و دواخستن بۆ ڕۆژی دیاریکراو پیشان دەدرێن.</p>
            </div>
            <label class="block">
                <span class="form-label mb-1 block">بەروار هەڵبژێرە</span>
                <input type="date" x-model="selectedDate" @change="changeScheduleDate($event.target.value)" class="glass-input w-full rounded-lg px-4 py-2 md:w-52">
            </label>
        </div>
        <div class="flex flex-col justify-between gap-4 border-t border-slate-200 pt-4 xl:flex-row xl:items-center">
            <input type="search" x-model="search" @input.debounce.100ms="search = $event.target.value" placeholder="گەڕان بەدوای بارهەڵگر، خاوەنی خەت، مۆبایل، VIN..." dir="rtl" autocomplete="off" class="glass-input w-full rounded-lg px-4 py-2 text-right text-sm xl:w-72">
            <div class="min-w-0">@include('gatekeeper.partials.sync-status')</div>
        </div>
    </div>
    @include('gatekeeper.partials.schedule-table')
</div>
@endsection
