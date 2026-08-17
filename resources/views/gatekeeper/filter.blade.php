@extends('layouts.layout')

@section('content')
<div class="glass-panel p-6" x-data="queueManager()" @keydown.escape.window="closeScheduleModal()">
    <div class="flex justify-between items-center mb-6">
        <div>
            <div class="text-xs font-bold text-blue-600 mb-1">پاڵاوتنی لیست</div>
            <h2 class="text-2xl font-bold text-slate-900">
                لیستی 
                @if($status == 'green') هاتووەکان @endif
                @if($status == 'red') نەهاتووەکان @endif
                @if($status == 'yellow') دواخراوەکان @endif
            </h2>
        </div>
        
        <a href="{{ route('gatekeeper.index') }}" class="btn-secondary px-4 py-2 rounded-lg font-semibold">گەڕانەوە</a>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="border-b border-white/10 text-gray-400">
                    <th class="py-3 px-4 font-normal">#</th>
                    <th class="py-3 px-4 font-normal">ژمارەی تەنکەر</th>
                    <th class="py-3 px-4 font-normal">ناوی شۆفێر</th>
                    <th class="py-3 px-4 font-normal">مۆبایل</th>
                    <th class="py-3 px-4 font-normal">کاتی دیاریکراو (داتابەیس)</th>
                    <th class="py-3 px-4 font-normal">بەرواری دیاریکراو</th>
                    <th class="py-3 px-4 font-normal">کاتی دیاریکراو</th>
                    <th class="py-3 px-4 font-normal">تێبینی</th>
                    <th class="py-3 px-4 font-normal">کردارەکان</th>
                </tr>
            </thead>
            <tbody>
                @foreach($queues as $queue)
                <tr class="border-b border-white/5 hover:bg-white/5 transition-colors 
                    {{ $status == 'green' ? 'queue-row-green' : '' }}
                    {{ $status == 'red' ? 'queue-row-red' : '' }}
                    {{ $status == 'yellow' ? 'queue-row-yellow' : '' }}
                ">
                    <td class="py-3 px-4">{{ $loop->iteration }}</td>
                    <td class="py-3 px-4 font-medium">{{ $queue->tanker->plate_number ?? '-' }}</td>
                    <td class="py-3 px-4">{{ $queue->tanker->driver->name ?? '-' }}</td>
                    <td class="py-3 px-4">{{ $queue->tanker->driver->phone ?? '-' }}</td>
                    <td class="py-3 px-4 text-gray-300">{{ $queue->created_at->format('Y-m-d H:i') }}</td>
                    <td class="py-3 px-4 text-yellow-300">{{ $queue->scheduled_date ?? '-' }}</td>
                    <td class="py-3 px-4 text-yellow-300">{{ $queue->scheduled_time ?? '-' }}</td>
                    <td class="py-3 px-4">{{ $queue->note ?? '-' }}</td>
                    <td class="py-3 px-4 flex gap-2">
                        @can('update queue status')
                        <!-- Green Button -->
                        <button type="button" @click="openScheduleModal({{ $queue->tanker->id }}, 'green', $el.closest('tr'))" 
                                class="px-3 py-1.5 rounded-lg text-sm transition-colors border"
                                :class="getStatus({{ $queue->tanker->id }}, '{{ $status }}') === 'green' ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-100'">
                            هاتن
                        </button>
                        <!-- Yellow Button -->
                        <button type="button" @click="openScheduleModal({{ $queue->tanker->id }}, 'yellow', $el.closest('tr'))" 
                                class="px-3 py-1.5 rounded-lg text-sm transition-colors border"
                                :class="getStatus({{ $queue->tanker->id }}, '{{ $status }}') === 'yellow' ? 'bg-amber-500 border-amber-500 text-white' : 'bg-amber-50 border-amber-200 text-amber-700 hover:bg-amber-100'">
                            دواخستن
                        </button>
                        <!-- Red Button -->
                        <button type="button" @click="updateStatus({{ $queue->tanker->id }}, 'red'); $el.closest('tr').style.display='none'" 
                                class="px-3 py-1.5 rounded-lg text-sm transition-colors border"
                                :class="getStatus({{ $queue->tanker->id }}, '{{ $status }}') === 'red' ? 'bg-rose-600 border-rose-600 text-white' : 'bg-rose-50 border-rose-200 text-rose-700 hover:bg-rose-100'">
                            نەهاتن
                        </button>
                        @else
                            <span class="text-slate-500">—</span>
                        @endcan
                    </td>
                </tr>
                @endforeach
                @if($queues->isEmpty())
                <tr>
                    <td colspan="5" class="py-8 text-center text-gray-400">هیچ داتایەک نییە لەم لیستەدا</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    @can('update queue status')
    <!-- Scheduling Modal -->
    <div x-show="showScheduleModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div @click.away="closeScheduleModal()" class="glass-card w-full max-w-sm p-6">
            <h3 class="text-xl font-bold text-slate-900 mb-4">دیاریکردنی کات</h3>

            <div class="space-y-4">
                <div>
                    <label class="form-label block mb-1">بەروار</label>
                    <input type="date" x-model="scheduleDate" class="glass-input w-full px-4 py-2 rounded-lg" required>
                </div>
                <div>
                    <label class="form-label block mb-1">کات</label>
                    <select x-model="scheduleTime" class="glass-input w-full px-4 py-2 rounded-lg" required>
                        <option value="5:30 بەیانی">5:30 بەیانی</option>
                        <option value="12:00 نیوەڕۆ">12:00 نیوەڕۆ</option>
                    </select>
                </div>
            </div>

            <div class="pt-6 flex justify-end space-x-3 space-x-reverse">
                <button type="button" @click="closeScheduleModal()" class="btn-secondary px-4 py-2 rounded-lg">پاشگەزبوونەوە</button>
                <button type="button" @click="confirmSchedule()" class="btn-primary px-6 py-2 rounded-lg font-medium">پاشەکەوتکردن</button>
            </div>
        </div>
    </div>
    @endcan
</div>

@push('scripts')
<script>
function queueManager() {
    return {
        online: navigator.onLine,
        localStatuses: JSON.parse(localStorage.getItem('queueStatuses') || '{}'),
        pendingSyncs: JSON.parse(localStorage.getItem('pendingSyncs') || '[]'),
        showScheduleModal: false,
        schedulingTankerId: null,
        schedulingStatus: null,
        schedulingRow: null,
        scheduleDate: new Date().toISOString().split('T')[0],
        scheduleTime: '5:30 بەیانی',
        
        init() {
            window.addEventListener('online', () => {
                this.online = true;
                this.syncPending();
            });
            window.addEventListener('offline', () => {
                this.online = false;
            });
            
            if (this.online && this.pendingSyncs.length > 0) {
                this.syncPending();
            }
        },
        
        getStatus(tankerId, serverStatus) {
            return this.localStatuses[tankerId] || serverStatus;
        },
        
        getRowClass(tankerId, serverStatus) {
            const status = this.getStatus(tankerId, serverStatus);
            if (status === 'green') return 'queue-row-green';
            if (status === 'red') return 'queue-row-red';
            if (status === 'yellow') return 'queue-row-yellow';
            return '';
        },
        
        openScheduleModal(tankerId, status, row) {
            this.schedulingTankerId = tankerId;
            this.schedulingStatus = status;
            this.schedulingRow = row;
            this.scheduleDate = new Date().toISOString().split('T')[0];
            this.scheduleTime = '5:30 بەیانی';
            this.showScheduleModal = true;
        },

        closeScheduleModal() {
            this.showScheduleModal = false;
            this.schedulingTankerId = null;
            this.schedulingStatus = null;
            this.schedulingRow = null;
        },

        confirmSchedule() {
            if (!this.scheduleDate) return alert('تکایە بەروار دیاری بکە');

            const row = this.schedulingRow;
            this.updateStatus(this.schedulingTankerId, this.schedulingStatus, {
                scheduled_date: this.scheduleDate,
                scheduled_time: this.scheduleTime
            });

            if (row) row.style.display = 'none';
            this.closeScheduleModal();
        },

        updateStatus(tankerId, status, extraData = {}) {
            this.localStatuses[tankerId] = status;
            localStorage.setItem('queueStatuses', JSON.stringify(this.localStatuses));
            
            const payload = { tanker_id: tankerId, status: status, ...extraData };
            
            if (this.online) {
                this.sendRequest(payload);
            } else {
                this.pendingSyncs.push(payload);
                localStorage.setItem('pendingSyncs', JSON.stringify(this.pendingSyncs));
            }
        },
        
        async sendRequest(payload) {
            try {
                const response = await fetch(`/gatekeeper/queue/${payload.tanker_id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                if (!response.ok) throw new Error('Network response was not ok');
            } catch (error) {
                console.error('Error syncing:', error);
                this.pendingSyncs.push(payload);
                localStorage.setItem('pendingSyncs', JSON.stringify(this.pendingSyncs));
            }
        },
        
        async syncPending() {
            const syncs = [...this.pendingSyncs];
            this.pendingSyncs = [];
            localStorage.setItem('pendingSyncs', '[]');
            
            for (const payload of syncs) {
                await this.sendRequest(payload);
            }
        }
    }
}
</script>
@endpush
@endsection
