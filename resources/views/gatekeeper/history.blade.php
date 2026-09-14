@extends('layouts.layout')

@section('content')
@php
    $statusLabels = ['green' => 'هاتووە', 'yellow' => 'دواخراوە', 'red' => 'نەهاتووە', 'pending' => 'دیاری نەکراوە'];
    $statusClasses = ['green' => 'bg-emerald-100 text-emerald-700', 'yellow' => 'bg-amber-100 text-amber-700', 'red' => 'bg-rose-100 text-rose-700', 'pending' => 'bg-slate-100 text-slate-600'];
    $showCurrent = $month === now('Asia/Baghdad')->format('Y-m');
@endphp
<div class="space-y-5">
    <div class="glass-panel p-6">
        <div class="mb-5">
            <div class="mb-1 text-xs font-bold text-blue-600">ئەرشیفی دەروازە</div>
            <h2 class="text-2xl font-bold text-slate-900">مێژووی خەتەکان</h2>
            <p class="mt-1 text-slate-500">لیستی ئێستا و تۆمارە ئەرشیفکراوەکان لە یەک خشتەدا پیشان دەدرێن.</p>
        </div>

        <form method="GET" action="{{ route('gatekeeper.history') }}" class="grid grid-cols-1 items-end gap-4 md:grid-cols-4">
            <label><span class="form-label mb-1 block">مانگ هەڵبژێرە</span><input type="month" name="month" value="{{ $month }}" required class="glass-input w-full rounded-lg px-4 py-2"></label>
            <label>
                <span class="form-label mb-1 block">دۆخ</span>
                <select name="status" class="glass-input w-full rounded-lg px-4 py-2">
                    <option value="">هەموو دۆخەکان</option>
                    <option value="green" @selected(request('status') === 'green')>هاتووە</option>
                    <option value="yellow" @selected(request('status') === 'yellow')>دواخراوە</option>
                    <option value="red" @selected(request('status') === 'red')>نەهاتووە</option>
                    <option value="pending" @selected(request('status') === 'pending')>دیاری نەکراوە</option>
                </select>
            </label>
            <label><span class="form-label mb-1 block">گەڕان</span><input type="search" name="search" value="{{ request('search') }}" placeholder="خاوەن، تابلۆ، جۆر، مۆدێل، VIN..." class="glass-input w-full rounded-lg px-4 py-2"></label>
            <button type="submit" class="btn-primary rounded-lg px-5 py-2">پیشاندانی ئەنجام</button>
        </form>
    </div>

    <div class="space-y-3">
        <div class="flex items-center justify-between px-1">
            <div>
                <h3 class="text-lg font-bold text-slate-900">{{ $showCurrent ? 'لیستی ئێستا و مێژووی مانگ' : 'مێژووی مانگ' }}</h3>
                <p class="text-sm text-slate-500">تۆمارەکانی مانگی {{ $month }}</p>
            </div>
            <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
                {{ ($showCurrent ? $currentTankers->count() : 0) + $items->count() }} خەت
            </span>
        </div>

        <div class="glass-panel overflow-x-auto">
            <table class="w-full border-collapse text-right">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500">
                        <th class="px-4 py-3 font-normal">جۆری تۆمار / کات</th>
                        <th class="px-4 py-3 font-normal">ڕیزبەندی</th>
                        <th class="px-4 py-3 font-normal">خاوەنی خەت</th>
                        <th class="px-4 py-3 font-normal">مۆبایل</th>
                        <th class="px-4 py-3 font-normal">تابلۆ</th>
                        <th class="px-4 py-3 font-normal">جۆری بارهەڵگر</th>
                        <th class="px-4 py-3 font-normal">مۆدێلی بارهەڵگر</th>
                        <th class="px-4 py-3 font-normal">VIN</th>
                        <th class="px-4 py-3 font-normal">دۆخ</th>
                        <th class="px-4 py-3 font-normal">بەروار و کاتی دانراو</th>
                        <th class="px-4 py-3 font-normal">تێبینی</th>
                    </tr>
                </thead>
                <tbody>
                    @if($showCurrent)
                        @foreach($currentTankers as $tanker)
                            @php($currentStatus = $tanker->latestQueue?->status ?? 'pending')
                            <tr class="border-b border-slate-100 align-top hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-bold text-blue-700">ئێستا</span><div class="mt-1 text-xs text-slate-400">{{ $tanker->latestQueue?->updated_at?->timezone('Asia/Baghdad')->format('Y-m-d H:i') ?: '-' }}</div></td>
                                <td class="px-4 py-3 font-semibold">{{ $tanker->sequence_number }}</td>
                                <td class="px-4 py-3">{{ $tanker->sequence_owner ?: '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $tanker->sequence_owner_phone ?: '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-semibold">{{ $tanker->plate_number }}</td>
                                <td class="px-4 py-3">{{ $tanker->truck_type ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $tanker->truck_model ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $tanker->vin ?: '-' }}</td>
                                <td class="px-4 py-3"><span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClasses[$currentStatus] ?? $statusClasses['pending'] }}">{{ $statusLabels[$currentStatus] ?? $currentStatus }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $tanker->latestQueue?->scheduled_date ?: '-' }}<div class="text-xs text-slate-400">{{ $tanker->latestQueue?->scheduled_time ?: '' }}</div></td>
                                <td class="min-w-48 px-4 py-3">{{ $tanker->latestQueue?->note ?: '-' }}</td>
                            </tr>
                        @endforeach
                    @endif

                    @foreach($items as $item)
                        <tr class="border-b border-slate-100 align-top hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">ئەرشیف</span><div class="mt-1 font-semibold">{{ $item->archive->reset_at?->timezone('Asia/Baghdad')->format('Y-m-d H:i') }}</div><div class="text-xs text-slate-400">{{ $item->archive->resetter?->name ?: '-' }}</div></td>
                            <td class="px-4 py-3 font-semibold">{{ $item->sequence_number }}</td>
                            <td class="px-4 py-3">{{ $item->sequence_owner ?: '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $item->sequence_owner_phone ?: '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold">{{ $item->plate_number }}</td>
                            <td class="px-4 py-3">{{ $item->truck_type ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->truck_model ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->vin ?: '-' }}</td>
                            <td class="px-4 py-3"><span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClasses[$item->status] ?? $statusClasses['pending'] }}">{{ $statusLabels[$item->status] ?? $item->status }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $item->scheduled_date?->format('Y-m-d') ?: '-' }}<div class="text-xs text-slate-400">{{ $item->scheduled_time ?: '' }}</div></td>
                            <td class="min-w-48 px-4 py-3">{{ $item->note ?: '-' }}</td>
                        </tr>
                    @endforeach

                    @if((! $showCurrent || $currentTankers->isEmpty()) && $items->isEmpty())
                        <tr><td colspan="11" class="px-4 py-12 text-center text-slate-500">لەم ماوەیەدا هیچ تۆمارێک نەدۆزرایەوە.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $items->links() }}</div>
</div>
@endsection
