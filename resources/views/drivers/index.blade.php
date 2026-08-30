@extends('layouts.layout')

@section('content')
<div
    x-data="{
        showAddModal: false,
        showEditModal: false,
        editDriver: null,
        search: @js(request('search', '')),
        hasSearchResults: true,
        filterDrivers() {
            const query = this.search.trim().toLocaleLowerCase();
            let visibleRows = 0;

            this.$refs.driverRows.querySelectorAll('[data-driver-search]').forEach((row) => {
                const matches = query === '' || row.dataset.driverSearch.toLocaleLowerCase().includes(query);
                row.style.display = matches ? '' : 'none';

                if (matches) {
                    visibleRows++;
                }
            });

            this.hasSearchResults = visibleRows > 0;
        }
    }"
    x-init="$nextTick(() => filterDrivers())"
    class="space-y-6"
>
    <div class="flex justify-between items-center">
        <h2 class="text-2xl font-bold">بەڕێوەبردنی شۆفێرەکان</h2>
        <div class="flex items-center gap-4">
            <form action="{{ route('drivers.index') }}" method="GET" @submit.prevent="filterDrivers()" class="flex gap-2">
                <input type="search" name="search" x-model="search" @input.debounce.100ms="filterDrivers()" value="{{ request('search') }}" placeholder="گەڕان بەدوای شۆفێر..." dir="rtl" autocomplete="off" class="glass-input px-4 py-2 rounded-lg text-sm w-64 text-right">
                <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm font-semibold">گەڕان</button>
            </form>
            @can('create drivers')
            <button type="button" @click="showAddModal = true" class="btn-primary px-4 py-2 rounded-lg flex items-center space-x-2 space-x-reverse">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                <span>زیادکردنی شۆفێر</span>
            </button>
            @endcan
        </div>
    </div>

    <!-- Drivers Table -->
    <div class="glass-panel overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="border-b border-white/10 text-gray-400">
                    <th class="py-4 px-6 font-normal">#</th>
                    <th class="py-4 px-6 font-normal">ناوی شۆفێر</th>
                    <th class="py-4 px-6 font-normal">مۆبایل</th>
                    <th class="py-4 px-6 font-normal">مۆڵەتی شۆفێری</th>
                    <th class="py-4 px-6 font-normal">شەهادە</th>
                    <th class="py-4 px-6 font-normal">کردارەکان</th>
                </tr>
            </thead>
            <tbody x-ref="driverRows">
                @foreach($drivers as $driver)
                <tr data-driver-search="{{ $driver->name }} {{ $driver->phone }} {{ $driver->license_number }} {{ $driver->certificate_number }}" class="border-b border-white/5 hover:bg-white/5 transition-colors">
                    <td class="py-4 px-6">{{ $loop->iteration }}</td>
                    <td class="py-4 px-6 font-medium text-lg">{{ $driver->name }}</td>
                    <td class="py-4 px-6">{{ $driver->phone }}</td>
                    <td class="py-4 px-6">{{ $driver->license_number }}</td>
                    <td class="py-4 px-6">
                        @if($driver->has_certificate)
                            <span class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-1 rounded-full text-xs">
                                هەیەتی 
                                @if($driver->certificate_number)
                                    ({{ $driver->certificate_number }})
                                @endif
                            </span>
                        @else
                            <span class="bg-rose-50 border border-rose-200 text-rose-700 px-3 py-1 rounded-full text-xs">نییەتی</span>
                        @endif
                    </td>
                    <td class="py-4 px-6 space-x-2 space-x-reverse">
                        @can('edit drivers')
                        <button @click="editDriver = {{ $driver->toJson() }}; showEditModal = true" class="text-blue-400 hover:text-blue-300">دەستکاری</button>
                        @endcan
                        @can('delete drivers')
                        <form action="{{ route('drivers.destroy', $driver) }}" method="POST" class="inline" onsubmit="return confirm('دڵنیای لە سڕینەوەی ئەم شۆفێرە؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">سڕینەوە</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @endforeach
                @if($drivers->isEmpty())
                <tr>
                    <td colspan="6" class="py-8 text-center text-gray-400">هیچ شۆفێرێک نەدۆزرایەوە.</td>
                </tr>
                @else
                <tr x-cloak x-show="!hasSearchResults">
                    <td colspan="6" class="py-8 text-center text-gray-400">هیچ شۆفێرێک نەدۆزرایەوە.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Add Modal -->
    @can('create drivers')
    <div x-show="showAddModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="showAddModal = false" class="glass-card w-full max-w-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">زیادکردنی شۆفێر</h3>
                <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-900 text-2xl">&times;</button>
            </div>
            
            <form action="{{ route('drivers.store') }}" method="POST" class="space-y-4" x-data="{ hasCert: false }">
                @csrf
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ناوی شۆفێر</label>
                    <input type="text" name="name" required class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ژمارەی مۆبایل</label>
                    <input type="text" name="phone" required class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ژمارەی مۆڵەتی شۆفێری</label>
                    <input type="text" name="license_number" required class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div class="flex items-center mt-4">
                    <input id="has_certificate" type="checkbox" name="has_certificate" value="1" x-model="hasCert" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <label for="has_certificate" class="ml-2 mr-2 text-sm text-gray-400">شەهادەی هەیە؟</label>
                </div>
                
                <div x-show="hasCert" x-transition style="display: none;">
                    <label class="block text-sm text-gray-400 mb-1">ژمارەی شەهادە</label>
                    <input type="text" name="certificate_number" :required="hasCert" class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                
                <div class="pt-4 flex justify-end space-x-3 space-x-reverse">
                    <button type="button" @click="showAddModal = false" class="btn-secondary px-4 py-2 rounded-lg">پاشگەزبوونەوە</button>
                    <button type="submit" class="btn-primary px-6 py-2 rounded-lg font-medium">زیادکردن</button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    <!-- Edit Modal -->
    @can('edit drivers')
    <div x-show="showEditModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="showEditModal = false" class="glass-card w-full max-w-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">دەستکاری شۆفێر</h3>
                <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-900 text-2xl">&times;</button>
            </div>
            
            <form :action="editDriver ? '/drivers/' + editDriver.id : ''" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ناوی شۆفێر</label>
                    <input type="text" name="name" x-model="editDriver.name" required class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ژمارەی مۆبایل</label>
                    <input type="text" name="phone" x-model="editDriver.phone" required class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ژمارەی مۆڵەتی شۆفێری</label>
                    <input type="text" name="license_number" x-model="editDriver.license_number" required class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div class="flex items-center mt-4">
                    <input id="edit_has_certificate" type="checkbox" name="has_certificate" value="1" x-model="editDriver.has_certificate" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <label for="edit_has_certificate" class="ml-2 mr-2 text-sm text-gray-400">شەهادەی هەیە؟</label>
                </div>

                <div x-show="editDriver && editDriver.has_certificate" x-transition style="display: none;">
                    <label class="block text-sm text-gray-400 mb-1">ژمارەی شەهادە</label>
                    <input type="text" name="certificate_number" x-model="editDriver.certificate_number" :required="editDriver && editDriver.has_certificate" class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                
                <div class="pt-4 flex justify-end space-x-3 space-x-reverse">
                    <button type="button" @click="showEditModal = false" class="btn-secondary px-4 py-2 rounded-lg">پاشگەزبوونەوە</button>
                    <button type="submit" class="btn-primary px-6 py-2 rounded-lg font-medium">نوێکردنەوە</button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
