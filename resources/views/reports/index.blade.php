@extends('layouts.layout')

@section('content')
<div class="glass-panel p-4">
    <div class="flex justify-between items-center mb-3">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">ڕاپۆرتەکان</h2>
            <p class="text-gray-400 mt-1">ئەرشیفی ڕاپۆرتی سەرەکان</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="border-b border-white/10 text-gray-400">
                    <th class="py-3 px-4 font-normal">#</th>
                    <th class="py-3 px-4 font-normal">ناوی فایلی ڕاپۆرت</th>
                    <th class="py-3 px-4 font-normal">قەبارە</th>
                    <th class="py-3 px-4 font-normal">بەروار</th>
                    <th class="py-3 px-4 font-normal">کردارەکان</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                    <td class="py-3 px-4">{{ $loop->iteration }}</td>
                    <td class="py-3 px-4 font-semibold text-blue-600">{{ $report['name'] }}</td>
                    <td class="py-3 px-4">{{ $report['size'] }}</td>
                    <td class="py-3 px-4">{{ $report['time'] }}</td>
                    <td class="py-3 px-4">
                        @can('download reports')
                        <a href="{{ route('reports.download', $report['name']) }}" target="_blank" class="text-blue-700 hover:text-blue-800 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg inline-flex items-center gap-1 font-semibold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            بینین / داونلۆد
                        </a>
                        @else
                            <span class="text-slate-500">تەنها بینین</span>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="py-8 text-center text-gray-400">هیچ ڕاپۆرتێک نەدۆزرایەوە.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
