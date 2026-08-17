<div class="overflow-x-auto">
    <table class="w-full text-right border-collapse">
        <thead><tr class="border-b border-white/10 text-gray-400">
            <th class="py-3 px-4 font-normal">#</th><th class="py-3 px-4 font-normal">زنجیرە</th><th class="py-3 px-4 font-normal">ژمارەی تەنکەر</th><th class="py-3 px-4 font-normal">ناوی شۆفێر</th><th class="py-3 px-4 font-normal">شەهادە</th><th class="py-3 px-4 font-normal">بەروار</th><th class="py-3 px-4 font-normal">کات</th><th class="py-3 px-4 font-normal">تێبینی</th><th class="py-3 px-4 font-normal">کردارەکان</th>
        </tr></thead>
        <tbody>
            <template x-for="(tanker, index) in visibleTankers" :key="tanker.id">
                <tr class="border-b border-white/5 transition-colors" :class="getRowClass(tanker)">
                    <td class="py-3 px-4" x-text="index + 1"></td><td class="py-3 px-4" x-text="tanker.sequence_number || '-'"></td><td class="py-3 px-4 font-medium" x-text="tanker.plate_number || '-'"></td><td class="py-3 px-4" x-text="tanker.driver?.name || '-'"></td>
                    <td class="py-3 px-4"><span x-show="tanker.driver?.has_certificate" class="rounded-full bg-emerald-100 px-3 py-1 text-xs text-emerald-700">هەیەتی</span><span x-show="!tanker.driver?.has_certificate" class="rounded-full bg-rose-100 px-3 py-1 text-xs text-rose-700">نییەتی</span></td>
                    <td class="py-3 px-4" x-text="tanker.queue?.scheduled_date || '-'"></td><td class="py-3 px-4" x-text="tanker.queue?.scheduled_time || '-'"></td>
                    <td class="py-3 px-4">
                        @can('update queue notes')
                        <input type="text" :value="tanker.queue?.note || ''" @input.debounce.1000ms="updateNote(tanker.id, $el.value)" placeholder="تێبینی بنووسە..." class="glass-input w-full min-w-[150px] rounded-lg px-3 py-1 text-sm">
                        @else
                        <span x-text="tanker.queue?.note || '-'"></span>
                        @endcan
                    </td>
                    <td class="py-3 px-4">
                        @can('update queue status')
                        <div class="flex gap-2">
                            <button type="button" @click="openScheduleModal(tanker.id, 'green')" class="rounded-lg border px-3 py-1.5 text-sm transition-colors" :class="getStatus(tanker) === 'green' ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-100'">هاتن</button>
                            <button type="button" @click="openScheduleModal(tanker.id, 'yellow')" class="rounded-lg border px-3 py-1.5 text-sm transition-colors" :class="getStatus(tanker) === 'yellow' ? 'bg-amber-500 border-amber-500 text-white' : 'bg-amber-50 border-amber-200 text-amber-700 hover:bg-amber-100'">دواخستن</button>
                            <button type="button" @click="updateStatus(tanker.id, 'red')" class="rounded-lg border px-3 py-1.5 text-sm transition-colors" :class="getStatus(tanker) === 'red' ? 'bg-rose-600 border-rose-600 text-white' : 'bg-rose-50 border-rose-200 text-rose-700 hover:bg-rose-100'">نەهاتن</button>
                        </div>
                        @else<span class="text-slate-500">—</span>@endcan
                    </td>
                </tr>
            </template>
            <tr x-show="!ready"><td colspan="9" class="py-10 text-center text-slate-500">داتای ئۆفلاین ئامادە دەکرێت...</td></tr>
            <tr x-cloak x-show="ready && visibleTankers.length === 0"><td colspan="9" class="py-10 text-center text-slate-500">هیچ داتایەک نەدۆزرایەوە.</td></tr>
        </tbody>
    </table>
</div>
@can('update queue status')
<div x-cloak x-show="showScheduleModal" x-transition.opacity class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4">
    <div @click.away="closeScheduleModal()" class="glass-card w-full max-w-sm p-6">
        <h3 class="mb-4 text-xl font-bold text-slate-900">دیاریکردنی کات</h3>
        <div class="space-y-4">
            <div><label class="form-label mb-1 block">بەروار</label><input type="date" x-model="scheduleDate" class="glass-input w-full rounded-lg px-4 py-2" required></div>
            <div><label class="form-label mb-1 block">کات</label><select x-model="scheduleTime" class="glass-input w-full rounded-lg px-4 py-2" required><option value="5:30 بەیانی">5:30 بەیانی</option><option value="12:00 نیوەڕۆ">12:00 نیوەڕۆ</option></select></div>
        </div>
        <div class="flex justify-end space-x-3 space-x-reverse pt-6"><button type="button" @click="closeScheduleModal()" class="btn-secondary rounded-lg px-4 py-2">پاشگەزبوونەوە</button><button type="button" @click="confirmSchedule()" class="btn-primary rounded-lg px-6 py-2 font-medium">پاشەکەوتکردن</button></div>
    </div>
</div>
@endcan
