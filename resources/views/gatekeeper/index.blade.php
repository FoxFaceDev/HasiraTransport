@extends('layouts.layout')

@section('content')
<div class="glass-panel p-6" x-data="queueManager()" @keydown.escape.window="closeScheduleModal()">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <div class="text-xs font-bold text-blue-600 mb-1">بەڕێوەبردنی هاتوچۆ</div>
            <h2 class="text-2xl font-bold text-slate-900">کۆنترۆڵی دەروازە</h2>
            <p class="text-gray-400 mt-1">ڕێکخستنی سەرەی تەنکەرەکان</p>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-4 items-center w-full md:flex-1">
            <!-- Search -->
            <input type="text" x-model="search" placeholder="گەڕان بەدوای بارهەڵگر، شۆفێر..." dir="rtl" class="glass-input px-4 py-2 rounded-lg text-sm w-full sm:w-64 text-right">
            
            <div x-show="pendingSyncs.length > 0" class="text-yellow-400 flex items-center px-4 py-2 bg-yellow-400/10 rounded-lg whitespace-nowrap">
                <span x-text="pendingSyncs.length" class="mr-1"></span> گۆڕانکاری چاوەڕێی ئۆنلاین بوونە
            </div>

            <!-- Spacer to push the reset button to the left -->
            <div class="flex-grow"></div>

            @can('reset queue')
            <form action="{{ route('gatekeeper.reset') }}" method="POST" @submit.prevent="resetQueue($el)">
                @csrf
                <button type="submit" :disabled="isResetting" class="bg-rose-50 hover:bg-rose-100 border border-rose-200 disabled:cursor-not-allowed disabled:opacity-60 text-rose-700 px-4 py-2 rounded-xl flex items-center gap-2 whitespace-nowrap transition-colors font-semibold">
                    <svg x-show="!isResetting" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <svg x-show="isResetting" style="display: none;" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    <span x-text="isResetting ? 'ڕاپۆرت دروست دەکرێت...' : 'سفرکردنەوە'"></span>
                </button>
            </form>
            @endcan
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="border-b border-white/10 text-gray-400">
                    <th class="py-3 px-4 font-normal">#</th>
                    <th class="py-3 px-4 font-normal">زنجیرە</th>
                    <th class="py-3 px-4 font-normal">ژمارەی تەنکەر</th>
                    <th class="py-3 px-4 font-normal">ناوی شۆفێر</th>
                    <th class="py-3 px-4 font-normal">شەهادە</th>
                    <th class="py-3 px-4 font-normal">تێبینی</th>
                    <th class="py-3 px-4 font-normal">کردارەکان (Status)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tankers as $tanker)
                @php
                    $currentStatus = $tanker->latestQueue?->status ?? 'pending';
                    $currentNote = $tanker->latestQueue?->note ?? '';
                    $hasCertificate = $tanker->driver ? $tanker->driver->has_certificate : false;
                @endphp
                <tr class="border-b border-white/5 hover:bg-white/5 transition-colors" 
                    :class="getRowClass({{ $tanker->id }}, '{{ $currentStatus }}')"
                    x-show="matchesSearch({ plate: '{{ $tanker->plate_number }}', sequence: '{{ $tanker->sequence_number }}', driver: '{{ $tanker->driver->name ?? '' }}' })">
                    <td class="py-3 px-4">{{ $loop->iteration }}</td>
                    <td class="py-3 px-4">{{ $tanker->sequence_number }}</td>
                    <td class="py-3 px-4 font-medium">{{ $tanker->plate_number }}</td>
                    <td class="py-3 px-4">{{ $tanker->driver->name ?? '-' }}</td>
                    <td class="py-3 px-4">
                        @if($hasCertificate)
                            <span class="bg-green-500/20 text-green-400 px-3 py-1 rounded-full text-xs">هەیەتی</span>
                        @else
                            <span class="bg-red-500/20 text-red-400 px-3 py-1 rounded-full text-xs">نییەتی</span>
                        @endif
                    </td>
                    <td class="py-3 px-4">
                        @can('update queue notes')
                        <input type="text" 
                               value="{{ $currentNote }}"
                               @input.debounce.1000ms="updateNote({{ $tanker->id }}, $el.value)" 
                               placeholder="تێبینی بنووسە..." 
                               class="glass-input px-3 py-1 rounded-lg text-sm w-full min-w-[150px]">
                        @else
                            {{ $currentNote ?: '-' }}
                        @endcan
                    </td>
                    <td class="py-3 px-4 flex gap-2">
                        @can('update queue status')
                        <!-- Green Button -->
                        <button type="button" @click="openScheduleModal({{ $tanker->id }}, 'green')" 
                                class="px-3 py-1.5 rounded-lg text-sm transition-colors border"
                                :class="getStatus({{ $tanker->id }}, '{{ $currentStatus }}') === 'green' ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-100'">
                            هاتن
                        </button>
                        <!-- Yellow Button -->
                        <button type="button" @click="openScheduleModal({{ $tanker->id }}, 'yellow')" 
                                class="px-3 py-1.5 rounded-lg text-sm transition-colors border"
                                :class="getStatus({{ $tanker->id }}, '{{ $currentStatus }}') === 'yellow' ? 'bg-amber-500 border-amber-500 text-white' : 'bg-amber-50 border-amber-200 text-amber-700 hover:bg-amber-100'">
                            دواخستن
                        </button>
                        <!-- Red Button -->
                        <button type="button" @click="updateStatus({{ $tanker->id }}, 'red')" 
                                class="px-3 py-1.5 rounded-lg text-sm transition-colors border"
                                :class="getStatus({{ $tanker->id }}, '{{ $currentStatus }}') === 'red' ? 'bg-rose-600 border-rose-600 text-white' : 'bg-rose-50 border-rose-200 text-rose-700 hover:bg-rose-100'">
                            نەهاتن
                        </button>
                        @else
                            <span class="text-slate-500">—</span>
                        @endcan
                    </td>
                </tr>
                @endforeach
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
        search: '',
        localStatuses: JSON.parse(localStorage.getItem('queueStatuses') || '{}'),
        pendingSyncs: JSON.parse(localStorage.getItem('pendingSyncs') || '[]'),
        isResetting: false,
        
        // Modal state
        showScheduleModal: false,
        schedulingTankerId: null,
        schedulingStatus: null,
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

        matchesSearch(tanker) {
            if (this.search.trim() === '') return true;
            const query = this.search.toLowerCase();
            return (tanker.plate || '').toLowerCase().includes(query) ||
                   (tanker.sequence || '').toLowerCase().includes(query) ||
                   (tanker.driver || '').toLowerCase().includes(query);
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

        openScheduleModal(tankerId, status) {
            this.schedulingTankerId = tankerId;
            this.schedulingStatus = status;
            this.scheduleDate = new Date().toISOString().split('T')[0];
            this.scheduleTime = '5:30 بەیانی';
            this.showScheduleModal = true;
        },

        closeScheduleModal() {
            this.showScheduleModal = false;
            this.schedulingTankerId = null;
            this.schedulingStatus = null;
        },

        confirmSchedule() {
            if (!this.scheduleDate) return alert('تکایە بەروار دیاری بکە');
            
            this.updateStatus(this.schedulingTankerId, this.schedulingStatus, {
                scheduled_date: this.scheduleDate,
                scheduled_time: this.scheduleTime
            });
            
            this.closeScheduleModal();
        },
        
        updateStatus(tankerId, status, extraData = {}) {
            // Optimistic update locally
            this.localStatuses[tankerId] = status;
            localStorage.setItem('queueStatuses', JSON.stringify(this.localStatuses));
            
            const payload = { tanker_id: tankerId, status: status, ...extraData };
            
            if (this.online) {
                this.sendRequest('/gatekeeper/queue/', payload);
            } else {
                // Save to pending if offline
                this.pendingSyncs.push({ type: 'status', url: '/gatekeeper/queue/', payload });
                localStorage.setItem('pendingSyncs', JSON.stringify(this.pendingSyncs));
            }
        },

        updateNote(tankerId, note) {
            const payload = { tanker_id: tankerId, note: note };
            
            if (this.online) {
                this.sendRequest('/gatekeeper/queue/' + tankerId + '/note', payload);
            } else {
                this.pendingSyncs.push({ type: 'note', url: '/gatekeeper/queue/' + tankerId + '/note', payload });
                localStorage.setItem('pendingSyncs', JSON.stringify(this.pendingSyncs));
            }
        },
        
        async sendRequest(baseUrl, payload) {
            const url = baseUrl.endsWith('/') ? baseUrl + payload.tanker_id : baseUrl;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                if (!response.ok) throw new Error('Network response was not ok');
                return true;
            } catch (error) {
                console.error('Error syncing:', error);
                // Fallback to offline if it fails
                this.pendingSyncs.push({ type: baseUrl.includes('note') ? 'note' : 'status', url: baseUrl, payload });
                localStorage.setItem('pendingSyncs', JSON.stringify(this.pendingSyncs));
                return false;
            }
        },
        
        async syncPending() {
            const syncs = [...this.pendingSyncs];
            this.pendingSyncs = [];
            localStorage.setItem('pendingSyncs', '[]');
            
            for (const item of syncs) {
                // Support old format and new format
                const url = item.url ? item.url : '/gatekeeper/queue/';
                const payload = item.payload ? item.payload : item;
                await this.sendRequest(url, payload);
            }
        },

        async resetQueue(form) {
            if (!confirm('دڵنیای لە سفرکردنەوەی هەموو سەرەکان؟ پێش سفرکردنەوە ڕاپۆرتێکی PDF دروست و دادەبەزێنرێت.')) return;

            if (!navigator.onLine) {
                alert('بۆ دروستکردنی ڕاپۆرت و سفرکردنەوە، پێویستە ئینتەرنێت بەرقەرار بێت.');
                return;
            }

            this.isResetting = true;

            try {
                // Allow any debounced note edit to reach the server first.
                await new Promise(resolve => setTimeout(resolve, 1100));

                if (this.pendingSyncs.length > 0) {
                    await this.syncPending();
                }

                if (this.pendingSyncs.length > 0) {
                    throw new Error('Pending changes could not be synced.');
                }

                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/pdf'
                    }
                });

                if (!response.ok) throw new Error('The report could not be created.');

                const blob = await response.blob();
                const disposition = response.headers.get('Content-Disposition') || '';
                const nameMatch = disposition.match(/filename="?([^";]+)"?/i);
                const fileName = nameMatch ? nameMatch[1] : 'tanker_queue_report.pdf';
                const downloadUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');

                link.href = downloadUrl;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(downloadUrl);

                this.localStatuses = {};
                this.pendingSyncs = [];
                localStorage.removeItem('queueStatuses');
                localStorage.removeItem('pendingSyncs');
                window.location.reload();
            } catch (error) {
                console.error('Queue reset failed:', error);
                alert('ڕاپۆرت دروست نەکرا و سەرەکان سفر نەکرانەوە. تکایە دووبارە هەوڵ بدەوە.');
            } finally {
                this.isResetting = false;
            }
        }
    }
}
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').then(() => {
        console.log('Service Worker Registered');
    });
}
</script>
@endpush
@endsection
