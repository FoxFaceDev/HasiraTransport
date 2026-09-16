@extends('layouts.layout')

@section('content')
<div
    x-data="{
        showAddModal: false,
        showActionsModal: false,
        showEditModal: false,
        showSaleModal: false,
        showHistoryModal: false,
        actionsTanker: null,
        editTanker: null,
        saleTanker: null,
        historyTanker: null,
        saleData: {},
        tankerSearch: @js(request('search', '')),
        hasSearchResults: true,
        openOperation(tanker, type = 'sale_with_truck') {
            this.saleTanker = tanker;
            this.saleData = {
                operation_type: type,
                document_number: '',
                new_owner: '',
                new_owner_phone: '',
                new_plate_number: '',
                new_vin: '',
                new_truck_type: '',
                new_truck_model: '',
                new_truck_color: '',
                transferred_at: '{{ now('Asia/Baghdad')->toDateString() }}',
                seller_national_id: '',
                seller_security_code: '',
                seller_agent: '',
                seller_agency_number: '',
                seller_document_date: '',
                buyer_national_id: '',
                buyer_security_code: '',
                buyer_agent: '',
                buyer_agency_number: '',
                buyer_document_date: '',
                note: '',
            };
            this.showSaleModal = true;
        },
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
    class="space-y-4"
>
    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
        <div>
            <h2 class="text-2xl font-bold">خەتەکان</h2>
            <p class="text-gray-400 mt-1">کۆی گشتی خەتەکان: {{ $tankerCount }} / {{ $maxTankers }}</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
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
            <div class="glass-card p-2 flex items-center gap-3 ml-2">
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
                    <label class="block text-sm text-gray-400 mb-1">مۆدێلی بارهەڵگر</label>
                    <input type="text" name="truck_model" inputmode="numeric" class="glass-input w-full px-4 py-2 rounded-lg" required>
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
                    <th class="py-4 px-6 font-normal">مۆدێلی بارهەڵگر</th>
                    <th class="py-4 px-6 font-normal">ڕەنگی بارهەڵگر</th>
                    <th class="py-4 px-6 font-normal">کردارەکان</th>
                </tr>
            </thead>
            <tbody x-ref="tankerRows">
                @foreach($tankers as $tanker)
                @php($isBlocked = $tanker->blocked_at)
                <tr data-tanker-search="{{ $tanker->sequence_number }} {{ $tanker->sequence_owner }} {{ $tanker->sequence_owner_phone }} {{ $tanker->plate_number }} {{ $tanker->vin }} {{ $tanker->truck_type }} {{ $tanker->truck_model }} {{ $tanker->truck_color }}@if($isBlocked) بلۆککراوە@endif" class="border-b border-white/5 hover:bg-white/5 transition-colors {{ $isBlocked ? 'blocked-row' : '' }}">
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
                    <td class="py-4 px-6">{{ $tanker->truck_model ?: '-' }}</td>
                    <td class="py-4 px-6">{{ $tanker->truck_color ?: '-' }}</td>
                    <td class="min-w-[120px] py-4 px-6">
                        <button type="button" @click="actionsTanker = {{ $tanker->toJson() }}; showActionsModal = true" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">کردارەکان</button>
                    </td>
                </tr>
                @endforeach
                @if($tankers->isEmpty())
                <tr>
                    <td colspan="9" class="py-8 text-center text-gray-400">هیچ بارهەڵگرێک نەدۆزرایەوە.</td>
                </tr>
                @else
                <tr x-cloak x-show="!hasSearchResults">
                    <td colspan="9" class="py-8 text-center text-gray-400">هیچ بارهەڵگرێک نەدۆزرایەوە.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Actions Modal -->
    <div x-show="showActionsModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="showActionsModal = false" class="glass-card w-full max-w-md p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold">کردارەکان</h3>
                    <p class="mt-1 text-sm text-slate-500"><span x-text="actionsTanker?.sequence_number || '-'"></span> — <span x-text="actionsTanker?.plate_number || '-'"></span></p>
                </div>
                <button type="button" @click="showActionsModal = false" class="text-2xl text-slate-400 hover:text-slate-900">&times;</button>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @can('edit tankers')
                <button type="button" @click="editTanker = actionsTanker; showActionsModal = false; showEditModal = true" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-bold text-blue-700 transition hover:bg-blue-100">دەستکاری</button>
                <button type="button" @click="showActionsModal = false; openOperation(actionsTanker, 'sale_with_truck')" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100">فرۆشتن</button>
                <button type="button" @click="showActionsModal = false; openOperation(actionsTanker, 'truck_change')" class="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-bold text-cyan-700 transition hover:bg-cyan-100">گۆڕینی بارهەڵگر</button>

                <form x-show="actionsTanker?.blocked_at" :action="actionsTanker ? '{{ url('/tankers') }}/' + actionsTanker.id + '/block' : ''" method="POST" onsubmit="return confirm('دڵنیای لە لابردنی بلۆکی ئەم خەتە؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100">لابردنی بلۆک</button>
                </form>
                <form x-show="actionsTanker && !actionsTanker.blocked_at" :action="actionsTanker ? '{{ url('/tankers') }}/' + actionsTanker.id + '/block' : ''" method="POST" onsubmit="return confirm('دڵنیای لە بلۆککردنی ئەم خەتە؟')">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="w-full rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-700 transition hover:bg-amber-100">بلۆککردن</button>
                </form>
                @endcan

                <button type="button" @click="historyTanker = actionsTanker; showActionsModal = false; showHistoryModal = true" class="rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 text-sm font-bold text-violet-700 transition hover:bg-violet-100">مێژوو</button>

                @can('delete tankers')
                <form :action="actionsTanker ? '{{ url('/tankers') }}/' + actionsTanker.id : ''" method="POST" onsubmit="return confirm('دڵنیای لە سڕینەوەی ئەم بارهەڵگرە؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700 transition hover:bg-rose-100">سڕینەوە</button>
                </form>
                @endcan
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    @can('edit tankers')
    <div x-show="showEditModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <template x-if="editTanker">
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
                    <label class="block text-sm text-gray-400 mb-1">مۆدێلی بارهەڵگر</label>
                    <input type="text" name="truck_model" x-model="editTanker.truck_model" inputmode="numeric" required class="glass-input w-full px-4 py-2 rounded-lg">
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
        </template>
    </div>
    @endcan

    @can('edit tankers')
    <div x-show="showSaleModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="showSaleModal = false" class="glass-card max-h-[92vh] w-full max-w-5xl overflow-y-auto p-6">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold" x-text="saleData.operation_type === 'truck_change' ? 'گۆڕینی بارهەڵگر' : 'فرۆشتنی خەت'"></h3>
                    <p class="mt-1 text-sm text-slate-500">خاوەنی ئێستا: <span class="font-bold" x-text="saleTanker?.sequence_owner || '-'"></span></p>
                </div>
                <button type="button" @click="showSaleModal = false" class="text-2xl text-slate-400 hover:text-slate-900">&times;</button>
            </div>
            <form :action="saleTanker ? '/tankers/' + saleTanker.id + '/sell' : ''" method="POST" target="_blank" class="space-y-5">
                @csrf
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="form-label mb-1 block">جۆری کردار *</label>
                        <select name="operation_type" x-model="saleData.operation_type" class="glass-input w-full rounded-lg px-4 py-2" required>
                            <option value="sale_with_truck">فرۆشتنی خەت لەگەڵ هەمان بارهەڵگر</option>
                            <option value="sale_line_only">فرۆشتنی خەت و گۆڕینی بارهەڵگر</option>
                            <option value="truck_change">گۆڕینی بارهەڵگر - خاوەن وەک خۆی دەمێنێتەوە</option>
                        </select>
                    </div>
                    <div><label class="form-label mb-1 block">ژمارەی بەڵگەنامە (No) *</label><input type="text" name="document_number" x-model="saleData.document_number" required maxlength="100" inputmode="numeric" autocomplete="off" class="glass-input w-full rounded-lg px-4 py-2"></div>
                    <div><label class="form-label mb-1 block">بەرواری کردار *</label><input type="date" name="transferred_at" x-model="saleData.transferred_at" required class="glass-input w-full rounded-lg px-4 py-2"></div>
                </div>

                <div x-show="saleData.operation_type !== 'truck_change'" class="rounded-xl border border-slate-200 p-4">
                    <h4 class="mb-3 font-bold text-slate-800">زانیاری کڕیار</h4>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div><label class="form-label mb-1 block">ناوی کڕیار *</label><input type="text" name="new_owner" x-model="saleData.new_owner" :required="saleData.operation_type !== 'truck_change'" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">ژمارەی مۆبایل <span x-show="saleData.operation_type === 'sale_line_only'">*</span></label><input type="text" name="new_owner_phone" x-model="saleData.new_owner_phone" :required="saleData.operation_type === 'sale_line_only'" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">ژمارەی پێناس</label><input type="text" name="buyer_national_id" x-model="saleData.buyer_national_id" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">کۆدی ئاسایش</label><input type="text" name="buyer_security_code" x-model="saleData.buyer_security_code" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">الوکیل</label><input type="text" name="buyer_agent" x-model="saleData.buyer_agent" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">رقم الوکالة</label><input type="text" name="buyer_agency_number" x-model="saleData.buyer_agency_number" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">تاریخ</label><input type="date" name="buyer_document_date" x-model="saleData.buyer_document_date" class="glass-input w-full rounded-lg px-4 py-2"></div>
                    </div>
                </div>

                <div x-show="saleData.operation_type !== 'truck_change'" class="rounded-xl border border-slate-200 p-4">
                    <h4 class="mb-3 font-bold text-slate-800">زانیاری فرۆشیار</h4>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div><label class="form-label mb-1 block">ژمارەی پێناس</label><input type="text" name="seller_national_id" x-model="saleData.seller_national_id" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">کۆدی ئاسایش</label><input type="text" name="seller_security_code" x-model="saleData.seller_security_code" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">الوکیل</label><input type="text" name="seller_agent" x-model="saleData.seller_agent" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">رقم الوکالة</label><input type="text" name="seller_agency_number" x-model="saleData.seller_agency_number" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">تاریخ</label><input type="date" name="seller_document_date" x-model="saleData.seller_document_date" class="glass-input w-full rounded-lg px-4 py-2"></div>
                    </div>
                </div>

                <div x-show="saleData.operation_type === 'sale_line_only' || saleData.operation_type === 'truck_change'" class="rounded-xl border border-cyan-200 bg-cyan-50/40 p-4">
                    <h4 class="mb-3 font-bold text-cyan-900">زانیاری بارهەڵگری نوێ</h4>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div><label class="form-label mb-1 block">ژمارەی تابلۆ *</label><input type="text" name="new_plate_number" x-model="saleData.new_plate_number" :required="saleData.operation_type === 'sale_line_only' || saleData.operation_type === 'truck_change'" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">VIN *</label><input type="text" name="new_vin" x-model="saleData.new_vin" :required="saleData.operation_type === 'sale_line_only' || saleData.operation_type === 'truck_change'" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">جۆری بارهەڵگر *</label><input type="text" name="new_truck_type" x-model="saleData.new_truck_type" :required="saleData.operation_type === 'sale_line_only' || saleData.operation_type === 'truck_change'" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">مۆدێل *</label><input type="text" name="new_truck_model" x-model="saleData.new_truck_model" :required="saleData.operation_type === 'sale_line_only' || saleData.operation_type === 'truck_change'" class="glass-input w-full rounded-lg px-4 py-2"></div>
                        <div><label class="form-label mb-1 block">ڕەنگ *</label><input type="text" name="new_truck_color" x-model="saleData.new_truck_color" :required="saleData.operation_type === 'sale_line_only' || saleData.operation_type === 'truck_change'" class="glass-input w-full rounded-lg px-4 py-2"></div>
                    </div>
                    <p x-show="saleData.operation_type === 'sale_line_only'" class="mt-3 text-xs text-cyan-800">ڕەنگ لە بەڵگەنامەی فرۆشتنی جۆری دووەمدا چاپ ناکرێت، بەڵام لە سیستەم و مێژوودا هەڵدەگیرێت.</p>
                </div>

                <div><label class="form-label mb-1 block">تێبینی</label><textarea name="note" x-model="saleData.note" rows="2" class="glass-input w-full rounded-lg px-4 py-2"></textarea></div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showSaleModal = false" class="btn-secondary rounded-lg px-4 py-2">پاشگەزبوونەوە</button>
                    <button type="submit" class="btn-primary rounded-lg px-6 py-2 font-medium">تۆمارکردن و کردنەوەی بەڵگەنامە</button>
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
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="transfer.change_type === 'truck_change' ? 'bg-cyan-100 text-cyan-700' : (transfer.change_type === 'correction' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700')" x-text="({ sale_with_truck: 'فرۆشتنی خەت لەگەڵ بارهەڵگر', sale_line_only: 'فرۆشتنی خەت و گۆڕینی بارهەڵگر', truck_change: 'گۆڕینی بارهەڵگر', sale: 'فرۆشتن', correction: 'دەستکاری' })[transfer.change_type] || transfer.change_type"></span>
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span x-show="transfer.document_number" class="rounded-md bg-slate-100 px-2 py-1 font-bold text-slate-700" x-text="'No: ' + transfer.document_number"></span>
                                <span x-text="transfer.transferred_at.slice(0, 10) + (transfer.recorder ? ' — ' + transfer.recorder.name : '')"></span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="rounded-lg bg-rose-50 p-3"><div class="mb-1 text-xs font-bold text-rose-700">پێشتر</div><div class="font-semibold" x-text="transfer.previous_owner || '-'"></div><div class="text-sm text-slate-600" x-text="(transfer.previous_owner_phone || '-') + ' — ' + transfer.previous_plate_number"></div><div class="mt-1 text-xs text-slate-500" x-text="transfer.previous_truck_type + ' — مۆدێل: ' + (transfer.previous_truck_model || '-') + ' — VIN: ' + (transfer.previous_vin || '-') + ' — ' + (transfer.previous_truck_color || '-')"></div></div>
                            <div class="rounded-lg bg-emerald-50 p-3"><div class="mb-1 text-xs font-bold text-emerald-700">دوای کردار</div><div class="font-semibold" x-text="transfer.new_owner || '-'"></div><div class="text-sm text-slate-600" x-text="(transfer.new_owner_phone || '-') + ' — ' + transfer.new_plate_number"></div><div class="mt-1 text-xs text-slate-500" x-text="transfer.new_truck_type + ' — مۆدێل: ' + (transfer.new_truck_model || '-') + ' — VIN: ' + (transfer.new_vin || '-') + ' — ' + (transfer.new_truck_color || '-')"></div></div>
                        </div>
                        <div class="mt-2 text-xs text-slate-500" x-show="transfer.note" x-text="transfer.note"></div>
                        <a x-show="transfer.document_path" :href="'/tankers/' + historyTanker.id + '/transfers/' + transfer.id + '/document'" target="_blank" class="mt-3 inline-flex rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100">کردنەوەی بەڵگەنامەی چاپ</a>
                    </div>
                </template>
                <div x-show="!(historyTanker?.ownership_transfers || []).length" class="py-8 text-center text-slate-500">هێشتا هیچ فرۆشتنێک بۆ ئەم خەتە تۆمار نەکراوە.</div>
            </div>
        </div>
    </div>
</div>
@endsection
