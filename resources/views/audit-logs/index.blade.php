@extends('layouts.layout')

@section('content')
<div class="space-y-4">
    <section class="glass-panel p-4">
        <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-center">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">تۆماری کردەوەکانی بەکارهێنەران</h1>
                <p class="mt-1 text-slate-500">زیادکردن، دەستکاری، سڕینەوە، گۆڕینی دۆخ و کردارە گرنگەکانی بەکارهێنەران لێرە تۆمار دەکرێن.</p>
            </div>
            <div class="flex shrink-0 flex-row flex-nowrap gap-3 overflow-x-auto pb-1 text-center">
                <div class="min-w-36 rounded-xl border border-slate-200 bg-white px-4 py-2"><div class="text-lg font-bold text-slate-900">{{ number_format($totalLogs) }}</div><div class="whitespace-nowrap text-xs text-slate-500">هەموو تۆمارەکان</div></div>
                <div class="min-w-36 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2"><div class="text-lg font-bold text-blue-700">{{ number_format($todayLogs) }}</div><div class="whitespace-nowrap text-xs text-blue-600">ئەمڕۆ</div></div>
                <div class="min-w-36 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2"><div class="text-lg font-bold text-amber-700">{{ number_format($todayChanges) }}</div><div class="whitespace-nowrap text-xs text-amber-600">گۆڕانکارییەکانی ئەمڕۆ</div></div>
            </div>
        </div>
    </section>

    <section class="glass-panel p-4">
        <form method="GET" action="{{ route('audit-logs.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="ناو، ئیمەیڵ، کردار، IP..." class="glass-input rounded-lg px-3 py-2 xl:col-span-2">
            <select name="user_id" class="glass-input rounded-lg px-3 py-2">
                <option value="">هەموو بەکارهێنەران</option>
                @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }} — {{ $user->email }}</option>
                @endforeach
            </select>
            <select name="method" class="glass-input rounded-lg px-3 py-2" dir="ltr">
                <option value="">هەموو کردارەکان</option>
                @foreach(['POST' => 'زیادکردن یان کردار', 'PUT' => 'نوێکردنەوە', 'PATCH' => 'گۆڕانکاری', 'DELETE' => 'سڕینەوە'] as $method => $methodLabel)
                <option value="{{ $method }}" @selected(request('method') === $method)>{{ $methodLabel }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ request('from') }}" class="glass-input rounded-lg px-3 py-2" title="لە بەرواری">
            <input type="date" name="to" value="{{ request('to') }}" class="glass-input rounded-lg px-3 py-2" title="تا بەرواری">
            <div class="flex gap-2 xl:col-span-6">
                <button class="btn-primary rounded-lg px-5 py-2 font-semibold">پاڵاوتن</button>
                <a href="{{ route('audit-logs.index') }}" class="btn-secondary rounded-lg px-5 py-2 font-semibold">پاککردنەوە</a>
            </div>
        </form>
    </section>

    <section class="glass-panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] border-collapse text-right">
                <thead>
                    <tr>
                        <th class="px-4 py-3">کاتی سلێمانی</th>
                        <th class="px-4 py-3">بەکارهێنەر</th>
                        <th class="px-4 py-3">چی کردووە؟</th>
                        <th class="px-4 py-3">ئەنجام</th>
                        <th class="px-4 py-3">وردەکاری</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    @php
                        $statusClass = $log->status_code >= 400 ? 'text-rose-700 bg-rose-50' : 'text-emerald-700 bg-emerald-50';
                        $humanDetails = $log->humanDetails();
                        $localTime = $log->created_at?->copy()->setTimezone(config('audit.timezone'));
                    @endphp
                    <tr class="border-t border-slate-100 align-top">
                        <td class="whitespace-nowrap px-4 py-3" dir="ltr">
                            <div class="font-semibold">{{ $localTime?->format('Y-m-d') }}</div>
                            <div class="text-xs text-slate-500">{{ $localTime?->format('H:i:s') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-900">{{ $log->user_name ?: 'بەکارهێنەری سڕاوە' }}</div>
                            <div class="text-xs text-slate-500" dir="ltr">{{ $log->user_email }}</div>
                        </td>
                        <td class="max-w-xl px-4 py-3 text-base font-semibold leading-7 text-slate-800">{{ $log->humanDescription() }}</td>
                        <td class="px-4 py-3"><span class="inline-flex whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold {{ $statusClass }}">{{ $log->resultLabel() }}</span></td>
                        <td class="px-4 py-3">
                            <details class="max-w-md">
                                <summary class="cursor-pointer whitespace-nowrap font-semibold text-blue-700">زانیاری زیاتر</summary>
                                <div class="mt-3 min-w-72 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    @if($humanDetails)
                                    <dl class="space-y-2">
                                        @foreach($humanDetails as $label => $value)
                                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-2 last:border-0 last:pb-0">
                                            <dt class="text-xs font-bold text-slate-500">{{ $label }}</dt>
                                            <dd class="text-left text-sm text-slate-800" dir="auto">{{ $value }}</dd>
                                        </div>
                                        @endforeach
                                    </dl>
                                    @endif
                                    <div class="text-xs text-slate-600">
                                        <div><span class="font-bold">ئامێر:</span> {{ $log->deviceName() }}</div>
                                        <div><span class="font-bold">IP:</span> <span dir="ltr">{{ $log->ip_address ?: '-' }}</span></div>
                                    </div>
                                    <details class="border-t border-slate-200 pt-2">
                                        <summary class="cursor-pointer text-xs text-slate-500">زانیاری تەکنیکی</summary>
                                        <div class="mt-2 break-all text-left text-xs text-slate-500" dir="ltr">
                                            {{ $log->method }} · {{ $log->route_name ?: $log->path }} · {{ $log->status_code }} · {{ $log->duration_ms }}ms
                                        </div>
                                    </details>
                                </div>
                            </details>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">هیچ تۆمارێک نەدۆزرایەوە.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="border-t border-slate-100 px-5 py-4">{{ $logs->links() }}</div>
        @endif
    </section>
</div>
@endsection
