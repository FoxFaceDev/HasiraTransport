<div class="overflow-x-auto">
    <table class="w-full text-right border-collapse">
        <thead><tr class="border-b border-white/10 text-gray-400">
            <th class="py-3 px-4 font-normal">ڕیزبەندی</th><th class="py-3 px-4 font-normal">ژمارەی تەنکەر</th><th class="py-3 px-4 font-normal">خاوەنی خەت</th><th class="py-3 px-4 font-normal">مۆبایل</th><th class="py-3 px-4 font-normal">بەروار</th><th class="py-3 px-4 font-normal">کات</th><th class="py-3 px-4 font-normal">تێبینی</th><th class="py-3 px-4 font-normal">کردارەکان</th>
        </tr></thead>
        <tbody>
            <template x-for="(tanker, index) in visibleTankers" :key="tanker.id">
                <tr class="border-b border-white/5 transition-colors" :class="[getRowClass(tanker), getScheduleDayDividerClass(index, tanker)]">
                    <td class="py-3 px-4" x-text="tanker.sequence_number || '-'"></td>
                    <td class="py-3 px-4 font-medium">
                        <div class="flex items-center gap-2"><span x-text="tanker.plate_number || '-'"></span><span x-cloak x-show="tanker.blocked_at" class="blocked-badge">خەت بلۆککراوە</span></div>
                    </td>
                    <td class="py-3 px-4" x-text="tanker.sequence_owner || '-'"></td>
                    <td class="py-3 px-4">
                        @can('update gatekeeper phone')
                        <button type="button" @contextmenu.prevent="editPhone(tanker)" title="بۆ گۆڕینی ژمارەی مۆبایل کرتەی ڕاست بکە" class="cursor-context-menu rounded px-1 py-0.5 hover:bg-blue-50 hover:text-blue-700" x-text="tanker.sequence_owner_phone || '-'"></button>
                        @else
                        <span x-text="tanker.sequence_owner_phone || '-'"></span>
                        @endcan
                    </td>
                    <td class="whitespace-nowrap py-3 px-4" x-text="getScheduledDateLabel(tanker)"></td><td class="py-3 px-4" x-text="tanker.queue?.scheduled_time || '-'"></td>
                    <td class="py-3 px-4">
                        @can('update queue notes')
                        <input type="text" :value="tanker.queue?.note || ''" @input.debounce.1000ms="updateNote(tanker.id, $el.value)" placeholder="تێبینی بنووسە..." class="glass-input w-full min-w-[150px] rounded-lg px-3 py-1 text-sm">
                        @else
                        <span x-text="tanker.queue?.note || '-'"></span>
                        @endcan
                    </td>
                    <td class="min-w-[120px] py-3 px-4">
                        @if(auth()->user()->can('update queue status') || auth()->user()->can('block tankers from gatekeeper'))
                        <button type="button" @click="openActionsModal(tanker)" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">کردارەکان</button>
                        @else
                        <span class="text-slate-500">—</span>
                        @endif
                    </td>
                </tr>
            </template>
            <tr x-cloak x-show="visibleTankers.length === 0"><td colspan="8" class="py-10 text-center text-slate-500">هیچ داتایەک نەدۆزرایەوە.</td></tr>
        </tbody>
    </table>
</div>

<div x-cloak x-show="showActionsModal" x-transition.opacity class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4">
    <div @click.away="closeActionsModal()" class="glass-card w-full max-w-md p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold text-slate-900">کردارەکان</h3>
                <p class="mt-1 text-sm text-slate-500"><span x-text="actionsTanker?.sequence_number || '-'"></span> — <span x-text="actionsTanker?.plate_number || '-'"></span></p>
            </div>
            <button type="button" @click="closeActionsModal()" class="text-2xl text-slate-400 hover:text-slate-900">&times;</button>
        </div>

        @can('update queue status')
        <div x-cloak x-show="actionsTanker && !isBlocked(actionsTanker)" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <button type="button" @click="getStatus(actionsTanker) === 'green' ? revertStatus(actionsTanker.id) : openScheduleModal(actionsTanker.id, 'green'); closeActionsModal()" class="rounded-lg border px-4 py-3 text-sm font-bold transition" :class="getStatus(actionsTanker) === 'green' ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-100'">هاتن</button>
            <button type="button" @click="getStatus(actionsTanker) === 'yellow' ? revertStatus(actionsTanker.id) : openScheduleModal(actionsTanker.id, 'yellow'); closeActionsModal()" class="rounded-lg border px-4 py-3 text-sm font-bold transition" :class="getStatus(actionsTanker) === 'yellow' ? 'bg-amber-500 border-amber-500 text-white' : 'bg-amber-50 border-amber-200 text-amber-700 hover:bg-amber-100'">دواخستن</button>
            <button type="button" @click="getStatus(actionsTanker) === 'red' ? revertStatus(actionsTanker.id) : updateStatus(actionsTanker.id, 'red'); closeActionsModal()" class="rounded-lg border px-4 py-3 text-sm font-bold transition" :class="getStatus(actionsTanker) === 'red' ? 'bg-rose-600 border-rose-600 text-white' : 'bg-rose-50 border-rose-200 text-rose-700 hover:bg-rose-100'">نەهاتن</button>
            <button type="button" @click="getStatus(actionsTanker) === 'departed' ? revertStatus(actionsTanker.id) : updateStatus(actionsTanker.id, 'departed'); closeActionsModal()" class="rounded-lg border px-4 py-3 text-sm font-bold transition" :class="getStatus(actionsTanker) === 'departed' ? 'bg-sky-600 border-sky-600 text-white' : 'bg-sky-50 border-sky-200 text-sky-700 hover:bg-sky-100'">ڕۆیشتن</button>
        </div>
        @endcan

        <div x-cloak x-show="actionsTanker && isBlocked(actionsTanker)" class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">ئەم خەتە بلۆککراوە؛ کردارەکانی دۆخ بەردەست نین.</div>

        @can('block tankers from gatekeeper')
        <div class="mt-3">
            <button type="button" x-cloak x-show="actionsTanker && !isBlocked(actionsTanker)" @click="setBlocked(actionsTanker, true)" class="w-full rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-700 transition hover:bg-amber-100">بلۆککردن</button>
            <button type="button" x-cloak x-show="actionsTanker && isBlocked(actionsTanker)" @click="setBlocked(actionsTanker, false)" class="w-full rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100">لابردنی بلۆک</button>
        </div>
        @endcan
    </div>
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
