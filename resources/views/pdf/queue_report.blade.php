<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ڕاپۆرتی تەنکەرەکان</title>
    <style>
        @page {
            footer: html_report-footer;
        }

        body {
            color: #25344f;
            direction: rtl;
            font-family: notokufiarabic, sans-serif;
            font-size: 10pt;
            line-height: 1.55;
        }

        .report-header {
            background-color: #ffffff;
            border: 1px solid #dce7f3;
            border-top: 6px solid #2f80ed;
            color: #1e3a5f;
            padding: 18px 22px 16px;
        }

        .report-header table,
        .report-meta table,
        .footer-table {
            border-collapse: collapse;
            margin: 0;
            width: 100%;
        }

        .report-header td,
        .report-meta td,
        .footer-table td {
            border: 0;
        }

        .brand-mark {
            background-color: #eaf3ff;
            color: #2563eb;
            font-size: 18pt;
            font-weight: bold;
            height: 44px;
            text-align: center;
            width: 44px;
        }

        h1 {
            color: #183153;
            font-size: 20pt;
            font-weight: bold;
            line-height: 1.25;
            margin: 0 0 3px;
        }

        .subtitle {
            color: #6b7f99;
            font-size: 9pt;
        }

        .report-meta {
            background-color: #eff6ff;
            border: 1px solid #d8e8fb;
            margin: 14px 0 12px;
            padding: 9px 14px;
        }

        .meta-label {
            color: #647995;
            font-size: 8pt;
        }

        .meta-value {
            color: #183153;
            font-weight: bold;
        }

        .data-table {
            border: 1px solid #dce5ef;
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .data-table th {
            background-color: #eaf3ff;
            border: 1px solid #cfdfef;
            border-bottom: 2px solid #7fb2ec;
            color: #1e3a5f;
            font-size: 8.3pt;
            font-weight: bold;
            padding: 8px 6px;
            text-align: center;
        }

        .data-table td {
            border: 1px solid #e2e9f0;
            padding: 7px 6px;
            text-align: right;
            vertical-align: middle;
        }

        .data-table tbody tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .center {
            text-align: center !important;
        }

        .muted {
            color: #94a3b8;
        }

        .empty-state {
            color: #64748b;
            font-size: 12pt;
            padding: 32px !important;
            text-align: center !important;
        }

        .footer-table {
            border-top: 1px solid #dce5ef;
            color: #718096;
            font-size: 8pt;
            padding-top: 5px;
        }

        .page-number {
            color: #31577d;
            font-weight: bold;
            text-align: left;
        }
    </style>
</head>
<body>
    <htmlpagefooter name="report-footer">
        <table class="footer-table" dir="rtl">
            <tr>
                <td>ڕاپۆرتی پێش سفرکردنەوەی سەرەکان - Hasira Transport</td>
                <td class="page-number">پەڕە <span>{PAGENO}</span> لە <span>{nbpg}</span></td>
            </tr>
        </table>
    </htmlpagefooter>

    <div class="report-header">
        <table dir="rtl">
            <tr>
                <td class="brand-mark">ح</td>
                <td style="padding-right: 14px;">
                    <h1>ڕاپۆرتی دۆخی تەنکەرەکان</h1>
                    <div class="subtitle">تۆماری دۆخی تەنکەرەکان پێش سفرکردنەوەی سەرەکان</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="report-meta">
        <table dir="rtl">
            <tr>
                <td width="34%">
                    <span class="meta-label">بەرواری دروستکردن:</span>
                    <span class="meta-value">{{ $generatedAt->format('Y-m-d') }}</span>
                </td>
                <td width="33%">
                    <span class="meta-label">کاتی دروستکردن:</span>
                    <span class="meta-value">{{ $generatedAt->format('H:i:s') }}</span>
                </td>
                <td width="33%">
                    <span class="meta-label">کۆی تەنکەرەکان:</span>
                    <span class="meta-value">{{ number_format($tankers->count()) }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="3" style="padding-top: 4px;">
                    <span class="meta-label">دروستکراوە لەلایەن:</span>
                    <span class="meta-value">{{ $generatedBy?->name ?? '-' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table" dir="rtl">
        <thead>
            <tr>
                <th width="10%">زنجیرە</th>
                <th width="14%">ژمارەی تەنکەر</th>
                <th width="17%">خاوەنی خەت</th>
                <th width="13%">مۆبایل</th>
                <th width="22%">تێبینی</th>
                <th width="13%">بەرواری دیاریکراو</th>
                <th width="11%">کاتی دیاریکراو</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tankers as $tanker)
            @php($queue = $tanker->latestQueue)
            <tr>
                <td class="center">{{ $tanker->sequence_number ?: '-' }}</td>
                <td class="center">{{ $tanker->plate_number ?: '-' }}</td>
                <td>{{ $tanker->sequence_owner ?: '-' }}</td>
                <td class="center">{{ $tanker->sequence_owner_phone ?: '-' }}</td>
                <td class="{{ $queue?->note ? '' : 'muted' }}">{{ $queue?->note ?: '-' }}</td>
                <td class="center {{ $queue?->scheduled_date ? '' : 'muted' }}">{{ $queue?->scheduled_date ? \Carbon\Carbon::parse($queue->scheduled_date)->format('Y-m-d') : '-' }}</td>
                <td class="center {{ $queue?->scheduled_time ? '' : 'muted' }}">{{ $queue?->scheduled_time ?: '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="empty-state">هیچ تەنکەرێک تۆمار نەکراوە.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
