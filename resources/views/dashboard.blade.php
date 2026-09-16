@extends('layouts.layout')

@section('content')
@php
    $statusLabels = ['pending' => 'دیاری نەکراوە', 'green' => 'هاتووە', 'yellow' => 'دواخراوە', 'red' => 'نەهاتووە', 'departed' => 'ڕۆیشتووە'];
    $statusBadgeClasses = ['pending' => 'bg-slate-100 text-slate-700', 'green' => 'bg-emerald-100 text-emerald-700', 'yellow' => 'bg-amber-100 text-amber-700', 'red' => 'bg-rose-100 text-rose-700', 'departed' => 'bg-sky-100 text-sky-700'];
    $distributionTotal = max(1, array_sum($statusCounts));
    $departedStop = ($statusCounts['departed'] / $distributionTotal) * 100;
    $greenStop = $departedStop + (($statusCounts['green'] / $distributionTotal) * 100);
    $yellowStop = $greenStop + (($statusCounts['yellow'] / $distributionTotal) * 100);
    $redStop = $yellowStop + (($statusCounts['red'] / $distributionTotal) * 100);
@endphp

<div class="space-y-4">
    <section class="dashboard-hero overflow-hidden rounded-2xl p-5 text-white">
        <div class="relative z-10 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <div>
                <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-bold"><span class="h-2 w-2 rounded-full bg-emerald-300"></span>داتای ڕاستەوخۆ</div>
                <h1 class="text-2xl font-bold">داشبۆردی بەڕێوەبردنی هاتوچۆ</h1>
                <p class="mt-1 max-w-2xl text-sm text-blue-100">پوختەی دۆخی هەموو خەتەکان، هاتوچۆی ئەمڕۆ و پلانی ڕۆژانی داهاتوو لە یەک شوێن.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-2 text-center backdrop-blur-sm"><div class="text-xs text-blue-100">ئەمڕۆ</div><div class="mt-0.5 font-bold" dir="ltr">{{ $today->format('Y-m-d') }}</div></div>
                @can('view gatekeeper')<a href="{{ route('gatekeeper.index') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-blue-700 shadow-lg shadow-blue-950/20 transition hover:bg-blue-50">کۆنترۆڵی دەروازە</a>@endcan
            </div>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="dashboard-stat-card border-blue-200 bg-gradient-to-br from-blue-50 to-white">
            <div class="dashboard-stat-icon bg-blue-100 text-blue-700"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h11v9H3V7Zm11 3h3.5l3 3v3H14v-6ZM6.5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg></div>
            <div><p class="text-xs font-semibold text-slate-500">هەموو خەتەکان</p><p class="mt-1 text-3xl font-black text-slate-900">{{ number_format($totalTankers) }}</p><p class="mt-1 text-xs text-slate-500">{{ number_format($totalTankers - $blockedTankers) }} خەتی چالاک</p></div>
        </article>
        <article class="dashboard-stat-card border-sky-200 bg-gradient-to-br from-sky-50 to-white">
            <div class="dashboard-stat-icon bg-sky-100 text-sky-700"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-5-5 5 5-5 5"/></svg></div>
            <div><p class="text-xs font-semibold text-slate-500">ڕۆیشتووەکانی ئەمڕۆ</p><p class="mt-1 text-3xl font-black text-sky-700">{{ number_format($departedToday) }}</p><p class="mt-1 text-xs font-semibold text-sky-600">{{ $completionRate }}٪ لە پلانی ئەمڕۆ</p></div>
        </article>
        <article class="dashboard-stat-card border-violet-200 bg-gradient-to-br from-violet-50 to-white">
            <div class="dashboard-stat-icon bg-violet-100 text-violet-700"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 2v3m8-3v3M3 9h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg></div>
            <div><p class="text-xs font-semibold text-slate-500">پلانی ئەمڕۆ</p><p class="mt-1 text-3xl font-black text-violet-700">{{ number_format($scheduledToday) }}</p><p class="mt-1 text-xs text-slate-500">هاتووە، دواخراوە و ڕۆیشتووە</p></div>
        </article>
        <article class="dashboard-stat-card border-rose-200 bg-gradient-to-br from-rose-50 to-white">
            <div class="dashboard-stat-icon bg-rose-100 text-rose-700"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.4 5.6a9 9 0 1 1-12.8 0 9 9 0 0 1 12.8 0ZM5.8 18.2 18.2 5.8"/></svg></div>
            <div><p class="text-xs font-semibold text-slate-500">خەتە بلۆککراوەکان</p><p class="mt-1 text-3xl font-black text-rose-700">{{ number_format($blockedTankers) }}</p><p class="mt-1 text-xs text-slate-500">{{ $totalTankers > 0 ? round(($blockedTankers / $totalTankers) * 100) : 0 }}٪ لە هەموو خەتەکان</p></div>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-5">
        <article class="glass-panel p-5 xl:col-span-2">
            <div class="flex items-start justify-between gap-3"><div><h2 class="text-lg font-bold text-slate-900">دابەشبوونی دۆخی ئێستا</h2><p class="mt-1 text-xs text-slate-500">دۆخی نوێترین تۆماری هەر خەتێک</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ number_format(array_sum($statusCounts)) }} خەت</span></div>
            <div class="mt-5 grid items-center gap-5 sm:grid-cols-[10rem_1fr]">
                <div class="dashboard-donut mx-auto" style="background: conic-gradient(#0284c7 0 {{ $departedStop }}%, #10b981 {{ $departedStop }}% {{ $greenStop }}%, #f59e0b {{ $greenStop }}% {{ $yellowStop }}%, #f43f5e {{ $yellowStop }}% {{ $redStop }}%, #94a3b8 {{ $redStop }}% 100%);"><div><strong>{{ number_format($totalTankers) }}</strong><span>هەموو</span></div></div>
                <div class="space-y-2.5">
                    @foreach([['departed', 'bg-sky-600'], ['green', 'bg-emerald-500'], ['yellow', 'bg-amber-500'], ['red', 'bg-rose-500'], ['pending', 'bg-slate-400']] as [$status, $dotClass])
                    <div class="flex items-center justify-between gap-3 text-sm"><div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $dotClass }}"></span><span class="text-slate-600">{{ $statusLabels[$status] }}</span></div><strong class="text-slate-900">{{ number_format($statusCounts[$status]) }}</strong></div>
                    @endforeach
                </div>
            </div>
        </article>

        <article class="glass-panel p-5 xl:col-span-3">
            <div class="flex items-start justify-between gap-3"><div><h2 class="text-lg font-bold text-slate-900">ڕۆیشتن لە هەفتەی ئێستا</h2><p class="mt-1 text-xs text-slate-500">لە شەممە تا هەینی بەپێی ڕۆژ</p></div><div class="rounded-lg bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-700">کۆی گشتی: {{ number_format($departureTrend->sum('count')) }}</div></div>
            <div class="mt-5 flex h-48 items-end gap-2 border-b border-slate-200 px-1 pb-2 sm:gap-4">
                @foreach($departureTrend as $day)
                <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2"><span class="text-xs font-bold text-slate-700">{{ $day['count'] }}</span><div class="dashboard-bar w-full" style="height: {{ max(5, ($day['count'] / $maxTrend) * 100) }}%"></div><div class="text-center"><div class="truncate text-[10px] font-bold text-slate-600 sm:text-xs">{{ $day['label'] }}</div><div class="text-[9px] text-slate-400" dir="ltr">{{ substr($day['date'], 5) }}</div></div></div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-3">
        <article class="glass-panel overflow-hidden xl:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="text-lg font-bold text-slate-900">ڕۆیشتووەکانی ئەمڕۆ</h2><p class="mt-0.5 text-xs text-slate-500">دوایین خەتە ڕۆیشتووەکان بەپێی کاتی نوێکردنەوە</p></div>@can('view gatekeeper')<a href="{{ route('gatekeeper.filter', ['status' => 'departed', 'date' => $today->toDateString()]) }}" class="text-xs font-bold text-blue-700 hover:text-blue-900">بینینی هەموو ←</a>@endcan</div>
            <div class="overflow-x-auto"><table class="w-full border-collapse text-right"><thead><tr class="text-slate-500"><th class="px-4 py-3 font-normal">کۆدی حەسیرە</th><th class="px-4 py-3 font-normal">ژمارەی تەنکەر</th><th class="px-4 py-3 font-normal">خاوەنی خەت</th><th class="px-4 py-3 font-normal">کات</th></tr></thead><tbody>
                @forelse($departedTodayRecords->take(7) as $record)
                <tr class="border-t border-slate-100"><td class="px-4 py-3 font-bold text-slate-900">{{ $record['sequence_number'] ?: '-' }}</td><td class="px-4 py-3">{{ $record['plate_number'] ?: '-' }}</td><td class="px-4 py-3 text-slate-600">{{ $record['sequence_owner'] ?: '-' }}</td><td class="px-4 py-3 font-semibold text-sky-700">{{ $record['time'] ?: '-' }}</td></tr>
                @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">هێشتا هیچ خەتێک ئەمڕۆ ڕۆیشتن بۆ تۆمار نەکراوە.</td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="glass-panel p-5">
            <div><h2 class="text-lg font-bold text-slate-900">دابەشبوونی کاتی ئەمڕۆ</h2><p class="mt-1 text-xs text-slate-500">پلانی خەتەکان بەپێی کاتی دیاریکراو</p></div>
            <div class="mt-5 space-y-4">
                @foreach([['5:30 بەیانی', $timeSlots['early'], 'bg-amber-300', 'text-amber-800'], ['12:00 نیوەڕۆ', $timeSlots['noon'], 'bg-amber-500', 'text-amber-900'], ['کاتی تر', $timeSlots['other'], 'bg-slate-400', 'text-slate-700']] as [$label, $count, $barClass, $textClass])
                <div><div class="mb-1.5 flex items-center justify-between text-sm"><span class="font-semibold {{ $textClass }}">{{ $label }}</span><strong>{{ $count }}</strong></div><div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $barClass }}" style="width: {{ $scheduledToday > 0 ? ($count / $scheduledToday) * 100 : 0 }}%"></div></div></div>
                @endforeach
            </div>
            <div class="mt-6 rounded-xl border border-blue-100 bg-blue-50 p-4"><div class="flex items-center justify-between"><span class="text-sm font-bold text-blue-800">ڕێژەی تەواوبوون</span><span class="text-xl font-black text-blue-700">{{ $completionRate }}٪</span></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-blue-100"><div class="h-full rounded-full bg-blue-600" style="width: {{ $completionRate }}%"></div></div></div>
        </article>
    </section>

    <section class="grid gap-4 xl:grid-cols-2">
        <article class="glass-panel p-5">
            <div class="flex items-center justify-between"><div><h2 class="text-lg font-bold text-slate-900">پلانی ٧ ڕۆژی داهاتوو</h2><p class="mt-1 text-xs text-slate-500">هاتووە و دواخراوەکان بەپێی بەروار</p></div>@can('view gatekeeper')<a href="{{ route('gatekeeper.schedule') }}" class="text-xs font-bold text-blue-700">خشتەی هاتوچۆ ←</a>@endcan</div>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @forelse($upcomingSchedule as $day)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="flex items-center justify-between"><div><div class="font-bold text-slate-900">{{ $day['label'] }}</div><div class="text-[10px] text-slate-400" dir="ltr">{{ $day['date'] }}</div></div><span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-black text-blue-700">{{ $day['total'] }}</span></div><div class="mt-3 flex gap-2 text-[11px]"><span class="rounded-md bg-amber-100 px-2 py-1 text-amber-800">5:30 — {{ $day['early'] }}</span><span class="rounded-md bg-amber-200 px-2 py-1 text-amber-900">12:00 — {{ $day['noon'] }}</span></div></div>
                @empty
                <div class="col-span-2 rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">هیچ خەتێک بۆ ٧ ڕۆژی داهاتوو دیاری نەکراوە.</div>
                @endforelse
            </div>
        </article>

        <article class="glass-panel p-5">
            <div><h2 class="text-lg font-bold text-slate-900">دوایین گۆڕانکارییەکان</h2><p class="mt-1 text-xs text-slate-500">نوێترین دۆخە تۆمارکراوەکانی دەروازە</p></div>
            <div class="mt-4 divide-y divide-slate-100">
                @forelse($recentChanges as $queue)
                <div class="flex items-center justify-between gap-3 py-2.5 first:pt-0"><div class="min-w-0"><div class="truncate text-sm font-bold text-slate-900">{{ $queue->tanker?->plate_number ?: '-' }} <span class="font-normal text-slate-400">·</span> {{ $queue->tanker?->sequence_owner ?: '-' }}</div><div class="mt-0.5 text-[10px] text-slate-400">کۆد: {{ $queue->tanker?->sequence_number ?: '-' }} · {{ $queue->status_updated_at?->timezone('Asia/Baghdad')->format('Y-m-d H:i') }}</div></div><span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $statusBadgeClasses[$queue->status] ?? $statusBadgeClasses['pending'] }}">{{ $statusLabels[$queue->status] ?? $queue->status }}</span></div>
                @empty
                <div class="py-8 text-center text-sm text-slate-500">هێشتا گۆڕانکارییەک تۆمار نەکراوە.</div>
                @endforelse
            </div>
        </article>
    </section>
</div>
@endsection
