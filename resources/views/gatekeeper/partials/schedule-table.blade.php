<div class="overflow-x-auto">
    <table class="w-full text-right border-collapse">
        <thead>
            <tr class="border-b border-white/10 text-gray-400">
                <th class="py-3 px-4 font-normal">ڕیزبەندی</th>
                <th class="py-3 px-4 font-normal">ژمارەی تەنکەر</th>
                <th class="py-3 px-4 font-normal">خاوەنی خەت</th>
                <th class="py-3 px-4 font-normal">مۆبایل</th>
                <th class="py-3 px-4 font-normal">بەروار</th>
                <th class="py-3 px-4 font-normal">کات</th>
                <th class="py-3 px-4 font-normal">دۆخ</th>
            </tr>
        </thead>
        <tbody>
            <template x-for="tanker in visibleTankers" :key="tanker.id">
                <tr class="border-b border-white/5 transition-colors" :class="getScheduleRowClass(tanker)">
                    <td class="py-3 px-4" x-text="tanker.sequence_number || '-'"></td>
                    <td class="py-3 px-4 font-medium" x-text="tanker.plate_number || '-'"></td>
                    <td class="py-3 px-4" x-text="tanker.sequence_owner || '-'"></td>
                    <td class="py-3 px-4" x-text="tanker.sequence_owner_phone || '-'"></td>
                    <td class="py-3 px-4" x-text="tanker.queue?.scheduled_date || '-'"></td>
                    <td class="py-3 px-4" x-text="tanker.queue?.scheduled_time || '-'"></td>
                    <td class="py-3 px-4 font-bold" x-text="getStatusLabel(tanker)"></td>
                </tr>
            </template>
            <tr x-cloak x-show="visibleTankers.length === 0"><td colspan="7" class="py-10 text-center text-slate-500">هیچ خەتێک بۆ ئەم بەروارە دیاری نەکراوە.</td></tr>
        </tbody>
    </table>
</div>
