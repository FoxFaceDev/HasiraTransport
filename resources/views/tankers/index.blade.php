@extends('layouts.layout')

@section('content')
<div
    x-data="{
        showAddModal: false,
        showEditModal: false,
        showSaleModal: false,
        showHistoryModal: false,
        editTanker: null,
        saleTanker: null,
        historyTanker: null,
        saleData: {},
        tankerSearch: @js(request('search', '')),
        hasSearchResults: true,
        filterTankers() {
            const query = this.tankerSearch.trim().toLocaleLowerCase();
            let visibleRows = 0;

            this.$refs.tankerRows.querySelectorAll('[data-tanker-search]').forEach((row) => {
                const matches = query === '' || row.dataset.tankerSearch.toLocaleLowerCase().includes(query);
                row.style.display = matches ? '' : 'none';

                if (matches) {
                    visibleRows++;
                }
            });

            this.hasSearchResults = visibleRows > 0;
        }
    }"
    x-init="$nextTick(() => filterTankers())"
    class="space-y-6"
>
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold">خەتەکان</h2>
            <p class="text-gray-400 mt-1">کۆی گشتی خەتەکان: {{ $tankerCount }} / {{ $maxTankers }}</p>
        </div>
        
        <div class="flex items-center gap-4">
            <form action="{{ route('tankers.index') }}" method="GET" @submit.prevent="filterTankers()" class="flex gap-2">
                <input type="search" name="search" x-model="tankerSearch" @input.debounce.100ms="filterTankers()" value="{{ request('search') }}" placeholder="گەڕان بەدوای خەت..." dir="rtl" autocomplete="off" class="glass-input px-4 py-2 rounded-lg text-sm w-64 text-right">
                <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm font-semibold">گەڕان</button>
            </form>
            
            @can('create tankers')
            @if($tankerCount < $maxTankers)
            <button type="button" @click="showAddModal = true" class="btn-primary px-4 py-2 rounded-lg flex items-center space-x-2 space-x-reverse">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>زیادکردنی بارهەڵگر</span>
            </button>
            @endif
            @endcan

            @can('manage tanker settings')
            <div class="glass-card p-4 flex items-center gap-4 ml-4">
                <span class="text-sm">گۆڕینی سنور:</span>
                <form action="{{ route('settings.max-tankers') }}" method="POST" class="flex gap-2">
                    @csrf
                    <input type="number" name="max_tankers" value="{{ $maxTankers }}" class="glass-input px-3 py-1.5 rounded-lg w-24 text-center">
                    <button type="submit" class="btn-primary px-4 py-1.5 rounded-lg text-sm">پاشەکەوت</button>
                </form>
            </div>
            @endcan
        </div>
    </div>

    <!-- Add Modal -->
    @can('create tankers')
    <div x-show="showAddModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="showAddModal = false" class="glass-card w-full max-w-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">زیادکردنی بارهەڵگری نوێ</h3>
                <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-900 text-2xl">&times;</button>
            </div>
            
            <form action="{{ route('tankers.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ڕیزبەندی</label>
                    <input type="text" name="sequence_number" class="glass-input w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">خاوەنی ڕیزبەندی</label>
                    <input type="text" name="sequence_owner" class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ژمارەی مۆبایلی خاوەن</label>
                    <input type="text" name="sequence_owner_phone" class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ژمارەی تابلۆی بارهەڵگر</label>
                    <input type="text" name="plate_number" placeholder="1199 سلێمانی" class="glass-input w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">جۆری بارهەڵگر</label>
                    <input type="text" name="truck_type" class="glass-input w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">VIN</label>
                    <input type="text" name="vin" class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ڕەنگی بارهەڵگر</label>
                    <input type="text" name="truck_color" class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div class="pt-4 flex justify-end space-x-3 space-x-reverse">
                    <button type="button" @click="showAddModal = false" class="btn-secondary px-4 py-2 rounded-lg">پاشگەزبوونەوە</button>
                    <button type="submit" class="btn-primary px-6 py-2 rounded-lg font-medium">زیادکردن</button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    <!-- Tankers Table -->
    <div class="glass-panel overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="border-b border-white/10 text-gray-400">
                    <th class="py-4 px-6 font-normal">ڕیزبەندی</th>
                    <th class="py-4 px-6 font-normal">خاوەنی ڕیزبەندی</th>
                    <th class="py-4 px-6 font-normal">ژمارەی مۆبایلی خاوەن</th>
                    <th class="py-4 px-6 font-normal">ژمارەی تابلۆ</th>
                    <th class="py-4 px-6 font-normal">VIN</th>
                    <th class="py-4 px-6 font-normal">جۆری بارهەڵگر</th>
                    <th class="py-4 px-6 font-normal">ڕەنگی بارهەڵگر</th>
                    <th class="py-4 px-6 font-normal">کردارەکان</th>
                </tr>
            </thead>
            <tbody x-ref="tankerRows">
                @foreach($tankers as $tanker)
                @php($isBlocked = $tanker->blocked_at)
                <tr data-tanker-search="{{ $tanker->sequence_number }} {{ $tanker->sequence_owner }} {{ $tanker->sequence_owner_phone }} {{ $tanker->plate_number }} {{ $tanker->vin }} {{ $tanker->truck_type }} {{ $tanker->truck_color }}@if($isBlocked) بلۆککراوە@endif" class="border-b border-white/5 hover:bg-white/5 transition-colors {{ $isBlocked ? 'blocked-row' : '' }}">
                    <td class="py-4 px-6">{{ $tanker->sequence_number }}</td>
                    <td class="py-4 px-6">{{ $tanker->sequence_owner ?: '-' }}</td>
                    <td class="py-4 px-6">{{ $tanker->sequence_owner_phone ?: '-' }}</td>
                    <td class="py-4 px-6 font-medium text-lg">
                        <div class="flex items-center gap-2">
                            <span>{{ $tanker->plate_number }}</span>
                            @if($tanker->blocked_at)<span class="blocked-badge">خەت بلۆککراوە</span>@endif
                        </div>
                    </td>
                    <td class="py-4 px-6">{{ $tanker->vin ?: '-' }}</td>
                    <td class="py-4 px-6">{{ $tanker->truck_type }}</td>
                    <td class="py-4 px-6">{{ $tanker->truck_color ?: '-' }}</td>
                    <td class="min-w-[250px] py-4 px-6">
                        <div class="flex flex-nowrap items-center gap-2 whitespace-nowrap">
                        @can('edit tankers')
                        <button type="button" @click="editTanker = {{ $tanker->toJson() }}; showEditModal = true" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 transition hover:border-blue-300 hover:bg-blue-100">دەستکاری</button>
                        <button type="button" @click="saleTanker = {{ $tanker->toJson() }}; saleData = { new_owner: '', new_owner_phone: '', new_plate_number: saleTanker.plate_number, new_vin: saleTanker.vin, new_truck_type: saleTanker.truck_type, new_truck_color: saleTanker.truck_color, transferred_at: '{{ now('Asia/Baghdad')->toDateString() }}', note: '' }; showSaleModal = true" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100">فرۆشتن</button>
                        @if($tanker->blocked_at)
                        <form action="{{ route('tankers.unblock', $tanker) }}" method="POST" class="shrink-0" onsubmit="return confirm('دڵنیای لە لابردنی بلۆکی ئەم خەتە؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-100">لابردنی بلۆک</button>
                        </form>
                        @else
                        <form action="{{ route('tankers.block', $tanker) }}" method="POST" class="shrink-0" onsubmit="return confirm('دڵنیای لە بلۆککردنی ئەم خەتە؟')">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700 transition hover:border-amber-300 hover:bg-amber-100">بلۆککردن</button>
                        </form>
                        @endif
                        @endcan
                        <button type="button" @click="historyTanker = {{ $tanker->toJson() }}; showHistoryModal = true" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-700 transition hover:bg-violet-100">مێژوو</button>
                        @can('delete tankers')
                        <form action="{{ route('tankers.destroy', $tanker) }}" method="POST" class="shrink-0" onsubmit="return confirm('دڵنیای لە سڕینەوەی ئەم بارهەڵگرە؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 transition hover:border-rose-300 hover:bg-rose-100">سڕینەوە</button>
                        </form>
                        @endcan
                        </div>
                    </td>
                </tr>
                @endforeach
                @if($tankers->isEmpty())
                <tr>
                    <td colspan="8" class="py-8 text-center text-gray-400">هیچ بارهەڵگرێک نەدۆزرایەوە.</td>
                </tr>
                @else
                <tr x-cloak x-show="!hasSearchResults">
                    <td colspan="8" class="py-8 text-center text-gray-400">هیچ بارهەڵگرێک نەدۆزرایەوە.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Edit Modal -->
    @can('edit tankers')
    <div x-show="showEditModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="showEditModal = false" class="glass-card w-full max-w-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">دەستکاری بارهەڵگر</h3>
                <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-900 text-2xl">&times;</button>
            </div>
            
            <form :action="editTanker ? '/tankers/' + editTanker.id : ''" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ڕیزبەندی</label>
                    <input type="text" name="sequence_number" x-model="editTanker.sequence_number" required class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">خاوەنی ڕیزبەندی</label>
                    <input type="text" name="sequence_owner" x-model="editTanker.sequence_owner" class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ژمارەی مۆبایلی خاوەن</label>
                    <input type="text" name="sequence_owner_phone" x-model="editTanker.sequence_owner_phone" class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ژمارەی تابلۆی بارهەڵگر</label>
                    <input type="text" name="plate_number" x-model="editTanker.plate_number" required class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">VIN</label>
                    <input type="text" name="vin" x-model="editTanker.vin" class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">جۆری بارهەڵگر</label>
                    <input type="text" name="truck_type" x-model="editTanker.truck_type" required class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ڕەنگی بارهەڵگر</label>
                    <input type="text" name="truck_color" x-model="editTanker.truck_color" class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div class="pt-4 flex justify-end space-x-3 space-x-reverse">
                    <button type="button" @click="showEditModal = false" class="btn-secondary px-4 py-2 rounded-lg">پاشگەزبوونەوە</button>
                    <button type="submit" class="btn-primary px-6 py-2 rounded-lg font-medium">نوێکردنەوە</button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    @can('edit tankers')
    <div x-show="showSaleModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="showSaleModal = false" class="glass-card max-h-[90vh] w-full max-w-2xl overflow-y-auto p-6">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold">فرۆشتنی خەت</h3>
                    <p class="mt-1 text-sm text-slate-500">خاوەنی ئێستا: <span class="font-bold" x-text="saleTanker?.sequence_owner || '-'"></span></p>
                </div>
                <button type="button" @click="showSaleModal = false" class="text-2xl text-slate-400 hover:text-slate-900">&times;</button>
            </div>
            <form :action="saleTanker ? '/tankers/' + saleTanker.id + '/sell' : ''" method="POST" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @csrf
                <div><label class="form-label mb-1 block">ناوی خاوەنی نوێ *</label><input type="text" name="new_owner" x-model="saleData.new_owner" required class="glass-input w-full rounded-lg px-4 py-2"></div>
                <div><label class="form-label mb-1 block">ژمارەی مۆبایلی خاوەنی نوێ</label><input type="text" name="new_owner_phone" x-model="saleData.new_owner_phone" class="glass-input w-full rounded-lg px-4 py-2"></div>
                <div><label class="form-label mb-1 block">ژمارەی تابلۆ *</label><input type="text" name="new_plate_number" x-model="saleData.new_plate_number" required class="glass-input w-full rounded-lg px-4 py-2"></div>
                <div><label class="form-label mb-1 block">VIN</label><input type="text" name="new_vin" x-model="saleData.new_vin" class="glass-input w-full rounded-lg px-4 py-2"></div>
                <div><label class="form-label mb-1 block">جۆری بارهەڵگر *</label><input type="text" name="new_truck_type" x-model="saleData.new_truck_type" required class="glass-input w-full rounded-lg px-4 py-2"></div>
                <div><label class="form-label mb-1 block">ڕەنگی بارهەڵگر</label><input type="text" name="new_truck_color" x-model="saleData.new_truck_color" class="glass-input w-full rounded-lg px-4 py-2"></div>
                <div><label class="form-label mb-1 block">بەرواری فرۆشتن *</label><input type="date" name="transferred_at" x-model="saleData.transferred_at" required class="glass-input w-full rounded-lg px-4 py-2"></div>
                <div><label class="form-label mb-1 block">تێبینی</label><input type="text" name="note" x-model="saleData.note" class="glass-input w-full rounded-lg px-4 py-2"></div>
                <p class="rounded-lg bg-blue-50 p-3 text-sm text-blue-700 md:col-span-2">ئەگەر بارهەڵگرەکە نەگۆڕاوە، زانیارییەکانی وەک خۆیان بهێڵەوە. هەموو زانیارییە کۆن و نوێیەکان لە مێژوودا دەمێننەوە.</p>
                <div class="flex justify-end gap-3 pt-2 md:col-span-2">
                    <button type="button" @click="showSaleModal = false" class="btn-secondary rounded-lg px-4 py-2">پاشگەزبوونەوە</button>
                    <button type="submit" class="btn-primary rounded-lg px-6 py-2 font-medium">تۆمارکردنی فرۆشتن</button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    <div x-show="showHistoryModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="showHistoryModal = false" class="glass-card max-h-[90vh] w-full max-w-4xl overflow-y-auto p-6">
            <div class="mb-5 flex items-center justify-between">
                <div><h3 class="text-xl font-bold">مێژووی خاوەندارێتی خەت</h3><p class="mt-1 text-sm text-slate-500">ڕیزبەندی: <span class="font-bold" x-text="historyTanker?.sequence_number"></span></p></div>
                <button type="button" @click="showHistoryModal = false" class="text-2xl text-slate-400 hover:text-slate-900">&times;</button>
            </div>
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="text-xs font-bold text-emerald-700">خاوەنی ئێستا</div>
                <div class="mt-1 font-bold" x-text="historyTanker?.sequence_owner || '-'"></div>
                <div class="text-sm text-slate-600" x-text="(historyTanker?.sequence_owner_phone || '-') + ' — ' + (historyTanker?.plate_number || '-')"></div>
            </div>
            <div class="space-y-3">
                <template x-for="transfer in (historyTanker?.ownership_transfers || [])" :key="transfer.id">
                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="transfer.change_type === 'sale' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'" x-text="transfer.change_type === 'sale' ? 'فرۆشتن' : 'دەستکاری'"></span>
                            <span class="text-xs text-slate-500" x-text="transfer.transferred_at.slice(0, 10) + (transfer.recorder ? ' — ' + transfer.recorder.name : '')"></span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="rounded-lg bg-rose-50 p-3"><div class="mb-1 text-xs font-bold text-rose-700">پێشتر</div><div class="font-semibold" x-text="transfer.previous_owner || '-'"></div><div class="text-sm text-slate-600" x-text="(transfer.previous_owner_phone || '-') + ' — ' + transfer.previous_plate_number"></div><div class="mt-1 text-xs text-slate-500" x-text="transfer.previous_truck_type + ' — VIN: ' + (transfer.previous_vin || '-') + ' — ' + (transfer.previous_truck_color || '-')"></div></div>
                            <div class="rounded-lg bg-emerald-50 p-3"><div class="mb-1 text-xs font-bold text-emerald-700">دوای گواستنەوە</div><div class="font-semibold" x-text="transfer.new_owner || '-'"></div><div class="text-sm text-slate-600" x-text="(transfer.new_owner_phone || '-') + ' — ' + transfer.new_plate_number"></div><div class="mt-1 text-xs text-slate-500" x-text="transfer.new_truck_type + ' — VIN: ' + (transfer.new_vin || '-') + ' — ' + (transfer.new_truck_color || '-')"></div></div>
                        </div>
                        <div class="mt-2 text-xs text-slate-500" x-show="transfer.note" x-text="transfer.note"></div>
                    </div>
                </template>
                <div x-show="!(historyTanker?.ownership_transfers || []).length" class="py-8 text-center text-slate-500">هێشتا هیچ فرۆشتنێک بۆ ئەم خەتە تۆمار نەکراوە.</div>
            </div>
        </div>
    </div>
</div>
@endsection
