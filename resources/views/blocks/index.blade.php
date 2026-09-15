@extends('layouts.layout')

@section('content')
<div
    x-data="{
        activeTab: 'drivers',
        driverSearch: '',
        tankerSearch: '',
        hasDriverResults: true,
        hasTankerResults: true,
        filterRows(type) {
            const isDriver = type === 'drivers';
            const query = (isDriver ? this.driverSearch : this.tankerSearch).trim().toLocaleLowerCase();
            const rows = isDriver ? this.$refs.driverBlockRows : this.$refs.tankerBlockRows;
            let visibleRows = 0;

            rows.querySelectorAll('[data-block-search]').forEach((row) => {
                const matches = query === '' || row.dataset.blockSearch.toLocaleLowerCase().includes(query);
                row.style.display = matches ? '' : 'none';
                if (matches) visibleRows++;
            });

            if (isDriver) this.hasDriverResults = visibleRows > 0;
            else this.hasTankerResults = visibleRows > 0;
        },
        selectTab(tab) {
            this.activeTab = tab;
            this.$nextTick(() => this.filterRows(tab));
        }
    }"
    x-init="$nextTick(() => { filterRows('drivers'); filterRows('tankers'); })"
    class="glass-panel p-6"
>
    <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <div class="mb-1 text-xs font-bold text-amber-600">لیستی بلۆککراوەکان</div>
            <h2 class="text-2xl font-bold text-slate-900">بلۆککراوەکان</h2>
            <p class="mt-1 text-gray-400">شۆفێر و خەتە بلۆککراوەکان لێرە بەڕێوە ببە.</p>
        </div>
        <div class="w-full md:w-72">
            <input x-cloak x-show="activeTab === 'drivers'" type="search" x-model="driverSearch" @input.debounce.100ms="filterRows('drivers')" placeholder="گەڕان بەدوای شۆفێر..." dir="rtl" autocomplete="off" class="glass-input w-full rounded-lg px-4 py-2 text-right text-sm">
            <input x-cloak x-show="activeTab === 'tankers'" type="search" x-model="tankerSearch" @input.debounce.100ms="filterRows('tankers')" placeholder="گەڕان بەدوای خەت..." dir="rtl" autocomplete="off" class="glass-input w-full rounded-lg px-4 py-2 text-right text-sm">
        </div>
    </div>

    <div class="mb-5 inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
        <button type="button" @click="selectTab('drivers')" :class="activeTab === 'drivers' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'" class="flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-bold transition">
            <span>شۆفێرەکان</span>
            <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">{{ $drivers->count() }}</span>
        </button>
        <button type="button" @click="selectTab('tankers')" :class="activeTab === 'tankers' ? 'bg-white text-amber-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'" class="flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-bold transition">
            <span>خەتەکان</span>
            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">{{ $tankers->count() }}</span>
        </button>
    </div>

    <div x-show="activeTab === 'drivers'" class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead><tr class="border-b border-white/10 text-gray-400">
                <th class="py-3 px-4 font-normal">ناوی شۆفێر</th>
                <th class="py-3 px-4 font-normal">زانیاری</th>
                <th class="py-3 px-4 font-normal">کاتی بلۆککردن</th>
                <th class="py-3 px-4 font-normal">کردارەکان</th>
            </tr></thead>
            <tbody x-ref="driverBlockRows">
                @foreach($drivers as $driver)
                <tr data-block-search="{{ $driver->name }} {{ $driver->phone }} {{ $driver->license_number }} {{ $driver->certificate_number }}" class="border-b border-white/5 transition-colors hover:bg-white/5">
                    <td class="py-3 px-4 font-medium">{{ $driver->name }}</td>
                    <td class="py-3 px-4 text-slate-600">{{ $driver->phone ?: '-' }} · {{ $driver->license_number ?: '-' }} · {{ $driver->tankers_count }} خەت</td>
                    <td class="py-3 px-4 text-slate-500">{{ $driver->blocked_at?->format('Y-m-d H:i') }}</td>
                    <td class="py-3 px-4">
                        @can('edit drivers')
                        <form action="{{ route('drivers.unblock', $driver) }}" method="POST" class="inline" onsubmit="return confirm('دڵنیای لە لابردنی بلۆکی ئەم شۆفێرە؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100">لابردنی بلۆک</button>
                        </form>
                        @else<span class="text-slate-400">—</span>@endcan
                    </td>
                </tr>
                @endforeach
                @if($drivers->isEmpty())
                <tr><td colspan="4" class="py-10 text-center text-slate-500">هیچ شۆفێرێکی بلۆککراو نییە.</td></tr>
                @else
                <tr x-cloak x-show="!hasDriverResults"><td colspan="4" class="py-10 text-center text-slate-500">هیچ شۆفێرێک نەدۆزرایەوە.</td></tr>
                @endif
            </tbody>
        </table>
    </div>

    <div x-cloak x-show="activeTab === 'tankers'" class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead><tr class="border-b border-white/10 text-gray-400">
                <th class="py-3 px-4 font-normal">ژمارەی تابلۆ</th>
                <th class="py-3 px-4 font-normal">خاوەنی خەت</th>
                <th class="py-3 px-4 font-normal">مۆبایل</th>
                <th class="py-3 px-4 font-normal">زانیاری</th>
                <th class="py-3 px-4 font-normal">کاتی بلۆککردن</th>
                <th class="py-3 px-4 font-normal">کردارەکان</th>
            </tr></thead>
            <tbody x-ref="tankerBlockRows">
                @foreach($tankers as $tanker)
                <tr data-block-search="{{ $tanker->sequence_number }} {{ $tanker->sequence_owner }} {{ $tanker->sequence_owner_phone }} {{ $tanker->plate_number }} {{ $tanker->vin }} {{ $tanker->truck_type }} {{ $tanker->truck_model }} {{ $tanker->truck_color }} {{ $tanker->driver?->name }}" class="border-b border-white/5 transition-colors hover:bg-white/5">
                    <td class="py-3 px-4 font-medium">{{ $tanker->plate_number }}</td>
                    <td class="py-3 px-4">{{ $tanker->sequence_owner ?: '-' }}</td>
                    <td class="whitespace-nowrap py-3 px-4">{{ $tanker->sequence_owner_phone ?: '-' }}</td>
                    <td class="py-3 px-4 text-slate-600">ڕیزبەندی: {{ $tanker->sequence_number ?: '-' }} · {{ $tanker->truck_type ?: '-' }} · مۆدێل: {{ $tanker->truck_model ?: '-' }} · {{ $tanker->driver?->name ?: 'بێ شۆفێر' }}</td>
                    <td class="py-3 px-4 text-slate-500">{{ $tanker->blocked_at?->format('Y-m-d H:i') }}</td>
                    <td class="py-3 px-4">
                        @can('edit tankers')
                        <form action="{{ route('tankers.unblock', $tanker) }}" method="POST" class="inline" onsubmit="return confirm('دڵنیای لە لابردنی بلۆکی ئەم خەتە؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100">لابردنی بلۆک</button>
                        </form>
                        @else<span class="text-slate-400">—</span>@endcan
                    </td>
                </tr>
                @endforeach
                @if($tankers->isEmpty())
                <tr><td colspan="6" class="py-10 text-center text-slate-500">هیچ خەتێکی بلۆککراو نییە.</td></tr>
                @else
                <tr x-cloak x-show="!hasTankerResults"><td colspan="6" class="py-10 text-center text-slate-500">هیچ خەتێک نەدۆزرایەوە.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
