@extends('layouts.layout')

@section('content')
<div x-data="{ showAddModal: false, showEditModal: false, editTanker: null }" class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold">بەڕێوەبردنی بارهەڵگرەکان</h2>
            <p class="text-gray-400 mt-1">کۆی گشتی بارهەڵگرەکان: {{ $tankers->count() }} / {{ $maxTankers }}</p>
        </div>
        
        <div class="flex items-center gap-4">
            <form action="{{ route('tankers.index') }}" method="GET" class="flex gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="گەڕان بەدوای بارهەڵگر..." dir="rtl" class="glass-input px-4 py-2 rounded-lg text-sm w-64 text-right">
                <button type="submit" class="btn-secondary px-4 py-2 rounded-lg text-sm font-semibold">گەڕان</button>
            </form>
            
            @can('create tankers')
            @if($tankers->count() < $maxTankers)
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
                    <label class="block text-sm text-gray-400 mb-1">زنجیرە</label>
                    <input type="text" name="sequence_number" class="glass-input w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">خاوەنی زنجیرە</label>
                    <input type="text" name="sequence_owner" class="glass-input w-full px-4 py-2 rounded-lg">
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
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ناوی شۆفێر</label>
                    <div x-data="{
                        search: '',
                        open: false,
                        selectedId: '',
                        selectedName: '',
                        drivers: [
                            @foreach($drivers as $driver)
                                { id: '{{ $driver->id }}', name: '{{ $driver->name }}', phone: '{{ $driver->phone }}' },
                            @endforeach
                        ],
                        get filteredDrivers() {
                            if (this.search === '') return this.drivers;
                            return this.drivers.filter(d => d.name.includes(this.search) || d.phone.includes(this.search));
                        },
                        selectDriver(driver) {
                            this.selectedId = driver.id;
                            this.selectedName = driver.name + ' (' + driver.phone + ')';
                            this.search = '';
                            this.open = false;
                        }
                    }" class="relative">
                        <input type="hidden" name="driver_id" :value="selectedId" required>
                        
                        <div @click="open = !open" @click.away="open = false" class="glass-input w-full px-4 py-2 rounded-lg flex justify-between items-center cursor-pointer">
                            <span x-text="selectedName || 'شۆفێر هەڵبژێرە...'" :class="selectedName ? 'text-slate-900' : 'text-gray-400'"></span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                        
                        <div x-show="open" x-transition class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden" style="display: none;">
                            <div class="p-2 border-b border-slate-100">
                                <input type="text" x-model="search" placeholder="گەڕان..." class="glass-input w-full px-3 py-1.5 rounded-lg text-sm" @click.stop>
                            </div>
                            <ul class="max-h-48 overflow-y-auto p-1">
                                <template x-for="driver in filteredDrivers" :key="driver.id">
                                    <li @click="selectDriver(driver)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer rounded-lg text-sm transition-colors" x-text="driver.name + ' (' + driver.phone + ')'"></li>
                                </template>
                                <li x-show="filteredDrivers.length === 0" class="px-3 py-2 text-gray-400 text-sm text-center">هیچ شۆفێرێک نەدۆزرایەوە</li>
                            </ul>
                        </div>
                    </div>
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
                    <th class="py-4 px-6 font-normal">#</th>
                    <th class="py-4 px-6 font-normal">زنجیرە</th>
                    <th class="py-4 px-6 font-normal">خاوەنی زنجیرە</th>
                    <th class="py-4 px-6 font-normal">ژمارەی تابلۆ</th>
                    <th class="py-4 px-6 font-normal">VIN</th>
                    <th class="py-4 px-6 font-normal">جۆری بارهەڵگر</th>
                    <th class="py-4 px-6 font-normal">ڕەنگی بارهەڵگر</th>
                    <th class="py-4 px-6 font-normal">ناوی شۆفێر</th>
                    <th class="py-4 px-6 font-normal">کردارەکان</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tankers as $tanker)
                <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                    <td class="py-4 px-6">{{ $loop->iteration }}</td>
                    <td class="py-4 px-6">{{ $tanker->sequence_number }}</td>
                    <td class="py-4 px-6">{{ $tanker->sequence_owner ?: '-' }}</td>
                    <td class="py-4 px-6 font-medium text-lg">{{ $tanker->plate_number }}</td>
                    <td class="py-4 px-6">{{ $tanker->vin ?: '-' }}</td>
                    <td class="py-4 px-6">{{ $tanker->truck_type }}</td>
                    <td class="py-4 px-6">{{ $tanker->truck_color ?: '-' }}</td>
                    <td class="py-4 px-6 text-gray-300">
                        {{ $tanker->driver ? $tanker->driver->name : '-' }}
                    </td>
                    <td class="py-4 px-6 space-x-2 space-x-reverse">
                        @can('edit tankers')
                        <button @click="editTanker = {{ $tanker->toJson() }}; showEditModal = true" class="text-blue-400 hover:text-blue-300">دەستکاری</button>
                        @endcan
                        @can('delete tankers')
                        <form action="{{ route('tankers.destroy', $tanker) }}" method="POST" class="inline" onsubmit="return confirm('دڵنیای لە سڕینەوەی ئەم بارهەڵگرە؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-300">سڕینەوە</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @endforeach
                @if($tankers->isEmpty())
                <tr>
                    <td colspan="9" class="py-8 text-center text-gray-400">هیچ بارهەڵگرێک نەدۆزرایەوە.</td>
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
                    <label class="block text-sm text-gray-400 mb-1">زنجیرە</label>
                    <input type="text" name="sequence_number" x-model="editTanker.sequence_number" required class="glass-input w-full px-4 py-2 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">خاوەنی زنجیرە</label>
                    <input type="text" name="sequence_owner" x-model="editTanker.sequence_owner" class="glass-input w-full px-4 py-2 rounded-lg">
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
                <div>
                    <label class="block text-sm text-gray-400 mb-1">ناوی شۆفێر</label>
                    <div x-data="{
                        search: '',
                        open: false,
                        drivers: [
                            @foreach($drivers as $driver)
                                { id: '{{ $driver->id }}', name: '{{ $driver->name }}', phone: '{{ $driver->phone }}' },
                            @endforeach
                        ],
                        get filteredDrivers() {
                            if (this.search === '') return this.drivers;
                            return this.drivers.filter(d => d.name.includes(this.search) || d.phone.includes(this.search));
                        },
                        get selectedName() {
                            if (!editTanker || !editTanker.driver_id) return 'شۆفێر هەڵبژێرە...';
                            const driver = this.drivers.find(d => d.id == editTanker.driver_id);
                            return driver ? driver.name + ' (' + driver.phone + ')' : 'شۆفێر هەڵبژێرە...';
                        },
                        selectDriver(driver) {
                            editTanker.driver_id = driver.id;
                            this.search = '';
                            this.open = false;
                        }
                    }" class="relative">
                        <input type="hidden" name="driver_id" :value="editTanker ? editTanker.driver_id : ''" required>
                        
                        <div @click="open = !open" @click.away="open = false" class="glass-input w-full px-4 py-2 rounded-lg flex justify-between items-center cursor-pointer">
                            <span x-text="selectedName" :class="editTanker && editTanker.driver_id ? 'text-slate-900' : 'text-gray-400'"></span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                        
                        <div x-show="open" x-transition class="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden" style="display: none;">
                            <div class="p-2 border-b border-slate-100">
                                <input type="text" x-model="search" placeholder="گەڕان..." class="glass-input w-full px-3 py-1.5 rounded-lg text-sm" @click.stop>
                            </div>
                            <ul class="max-h-48 overflow-y-auto p-1">
                                <template x-for="driver in filteredDrivers" :key="driver.id">
                                    <li @click="selectDriver(driver)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer rounded-lg text-sm transition-colors" x-text="driver.name + ' (' + driver.phone + ')'"></li>
                                </template>
                                <li x-show="filteredDrivers.length === 0" class="px-3 py-2 text-gray-400 text-sm text-center">هیچ شۆفێرێک نەدۆزرایەوە</li>
                            </ul>
                        </div>
                    </div>
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
