<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'method',
        'route_name',
        'path',
        'subject_type',
        'subject_id',
        'subject_label',
        'request_data',
        'ip_address',
        'user_agent',
        'status_code',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function humanDescription(): string
    {
        $target = $this->subject_label
            ?? data_get($this->request_data, 'plate_number')
            ?? data_get($this->request_data, 'name');
        $target = $target ? ' «'.$target.'»' : '';

        $description = match ($this->route_name) {
            'login.attempt' => 'چووە ژوورەوەی سیستەم',
            'logout' => 'لە سیستەم دەرچوو',
            'dashboard' => 'پەڕەی سەرەکیی کردەوە',

            'drivers.index' => filled(data_get($this->request_data, 'search'))
                ? 'لە شۆفێرەکاندا بەدوای «'.data_get($this->request_data, 'search').'» گەڕا'
                : 'لیستی شۆفێرەکانی بینی',
            'drivers.store' => 'شۆفێرێکی نوێی زیاد کرد'.$target,
            'drivers.update' => 'زانیاری شۆفێرێکی دەستکاری کرد'.$target,
            'drivers.destroy' => 'شۆفێرێکی سڕییەوە'.$target,
            'drivers.block' => 'شۆفێرێکی بلۆک کرد'.$target,
            'drivers.unblock' => 'بلۆکی شۆفێرێکی لابرد'.$target,

            'tankers.index' => filled(data_get($this->request_data, 'search'))
                ? 'لە خەتەکاندا بەدوای «'.data_get($this->request_data, 'search').'» گەڕا'
                : 'لیستی خەتەکانی بینی',
            'tankers.store' => 'خەتێکی نوێی زیاد کرد'.$target,
            'tankers.update' => 'زانیاری خەتێکی دەستکاری کرد'.$target,
            'tankers.sell' => match (data_get($this->request_data, 'operation_type')) {
                'sale_with_truck' => 'خەتێکی لەگەڵ هەمان بارهەڵگر فرۆشت'.$target,
                'sale_line_only' => 'خەتێکی فرۆشت و بارهەڵگرەکەی گۆڕی'.$target,
                'truck_change' => 'بارهەڵگری خەتێکی گۆڕی'.$target,
                default => 'خەتێکی فرۆشت و خاوەندارێتی گواستەوە'.$target,
            },
            'tankers.destroy' => 'خەتێکی سڕییەوە'.$target,
            'tankers.block' => 'خەتێکی بلۆک کرد'.$target,
            'tankers.unblock' => 'بلۆکی خەتێکی لابرد'.$target,
            'settings.max-tankers' => 'سنووری ژمارەی خەتەکانی گۆڕی بۆ '.data_get($this->request_data, 'max_tankers', '-'),

            'blocks.index' => 'لیستی بلۆککراوەکانی بینی',
            'gatekeeper.index' => 'پەڕەی کۆنترۆڵی دەروازەی کردەوە',
            'gatekeeper.schedule' => 'لیستی خەتە دیاریکراوەکانی ڕۆژی «'.data_get($this->request_data, 'date', now('Asia/Baghdad')->toDateString()).'»ی بینی',
            'gatekeeper.history' => 'مێژووی دۆخی خەتەکانی بینی',
            'gatekeeper.filter' => 'لیستی دۆخی «'.$this->statusName($this->subject_label).'»ی بینی',
            'gatekeeper.update-status' => 'دۆخی خەتێکی گۆڕی بۆ «'.$this->statusName(data_get($this->request_data, 'status')).'»'.$target,
            'gatekeeper.update-note' => 'تێبینی خەتێکی نوێ کردەوە'.$target,
            'gatekeeper.sync.snapshot' => 'داتای کۆنترۆڵی دەروازەی نوێ کردەوە',
            'gatekeeper.sync.push' => 'گۆڕانکارییە ئۆفلاینەکانی هاوکات کردەوە',
            'gatekeeper.reset' => 'لیستی دەروازەی سفر کردەوە و ڕاپۆرتی دروست کرد',

            'reports.index' => 'لیستی ڕاپۆرتەکانی بینی',
            'reports.download' => 'ڕاپۆرتێکی داگرت'.$target,
            'profile.edit' => 'زانیاری هەژماری خۆی بینی',
            'profile.update' => 'زانیاری هەژماری خۆی نوێ کردەوە',
            'profile.destroy' => 'هەژماری خۆی سڕییەوە',

            'users.index' => 'لیستی بەکارهێنەرانی بینی',
            'users.store' => 'بەکارهێنەرێکی نوێی زیاد کرد'.$target,
            'users.update' => 'زانیاری بەکارهێنەرێکی دەستکاری کرد'.$target,
            'users.destroy' => 'بەکارهێنەرێکی سڕییەوە'.$target,
            'roles.index' => 'لیستی ڕۆڵ و دەسەڵاتەکانی بینی',
            'roles.store' => 'ڕۆڵێکی نوێی زیاد کرد'.$target,
            'roles.update' => 'ڕۆڵ و دەسەڵاتێکی دەستکاری کرد'.$target,
            'roles.destroy' => 'ڕۆڵێکی سڕییەوە'.$target,
            'audit-logs.index' => 'تۆماری چاودێریی بینی',

            default => $this->method === 'GET'
                ? 'پەڕەیەکی سیستەمی بینی'
                : 'گۆڕانکارییەکی لە سیستەم ئەنجام دا',
        };

        return match (true) {
            $this->status_code === 403 => 'هەوڵی ئەم کردارەی دا، بەڵام ڕێگەی پێنەدرا: '.$description,
            $this->status_code === 404 => 'هەوڵی ئەم کردارەی دا، بەڵام داتاکە نەدۆزرایەوە: '.$description,
            $this->status_code === 422 => 'هەوڵی ئەم کردارەی دا، بەڵام زانیارییەکان دروست نەبوون: '.$description,
            $this->status_code >= 500 => 'کردارەکە بەهۆی هەڵەی سیستەم تەواو نەبوو: '.$description,
            $this->status_code >= 400 => 'کردارەکە سەرکەوتوو نەبوو: '.$description,
            default => $description,
        };
    }

    /**
     * @return array<string, string>
     */
    public function humanDetails(): array
    {
        $labels = [
            'search' => 'وشەی گەڕان',
            'name' => 'ناو',
            'email' => 'ئیمەیڵ',
            'role' => 'ڕۆڵ',
            'phone' => 'ژمارەی مۆبایل',
            'license_number' => 'ژمارەی مۆڵەت',
            'has_certificate' => 'شەهادەی هەیە',
            'certificate_number' => 'ژمارەی شەهادە',
            'sequence_number' => 'ڕیزبەندی',
            'sequence_owner' => 'خاوەنی خەت',
            'sequence_owner_phone' => 'مۆبایلی خاوەنی خەت',
            'new_owner' => 'خاوەنی نوێ',
            'new_owner_phone' => 'مۆبایلی خاوەنی نوێ',
            'new_plate_number' => 'تابلۆی نوێ',
            'new_vin' => 'VINی نوێ',
            'new_truck_type' => 'جۆری بارهەڵگری نوێ',
            'new_truck_model' => 'مۆدێلی بارهەڵگری نوێ',
            'new_truck_color' => 'ڕەنگی بارهەڵگری نوێ',
            'operation_type' => 'جۆری کردار',
            'document_number' => 'ژمارەی بەڵگەنامە',
            'seller_national_id' => 'ژمارەی پێناسی فرۆشیار',
            'seller_security_code' => 'کۆدی ئاسایشی فرۆشیار',
            'seller_agent' => 'وەکیلی فرۆشیار',
            'seller_agency_number' => 'ژمارەی وەکالەتی فرۆشیار',
            'seller_document_date' => 'بەرواری بەڵگەی فرۆشیار',
            'buyer_national_id' => 'ژمارەی پێناسی کڕیار',
            'buyer_security_code' => 'کۆدی ئاسایشی کڕیار',
            'buyer_agent' => 'وەکیلی کڕیار',
            'buyer_agency_number' => 'ژمارەی وەکالەتی کڕیار',
            'buyer_document_date' => 'بەرواری بەڵگەی کڕیار',
            'transferred_at' => 'بەرواری فرۆشتن',
            'plate_number' => 'ژمارەی تابلۆ',
            'vin' => 'VIN',
            'truck_type' => 'جۆری بارهەڵگر',
            'truck_model' => 'مۆدێلی بارهەڵگر',
            'truck_color' => 'ڕەنگی بارهەڵگر',
            'status' => 'دۆخ',
            'scheduled_date' => 'بەرواری دیاریکراو',
            'scheduled_time' => 'کاتی دیاریکراو',
            'note' => 'تێبینی',
            'max_tankers' => 'سنووری خەتەکان',
            'remember' => 'لەبیرم بێت',
        ];

        $details = [];
        foreach ($this->request_data ?? [] as $key => $value) {
            if (in_array($key, ['_token', '_method', 'password', 'password_confirmation', 'current_password'], true)) {
                continue;
            }

            if ($key === 'operations' && is_array($value)) {
                $details['ژمارەی گۆڕانکارییە هاوکاتکراوەکان'] = (string) count($value);

                continue;
            }

            if (! array_key_exists($key, $labels)) {
                continue;
            }

            if ($key === 'status') {
                $value = $this->statusName((string) $value);
            } elseif (is_bool($value)) {
                $value = $value ? 'بەڵێ' : 'نەخێر';
            } elseif (is_array($value)) {
                $value = implode('، ', array_map('strval', $value));
            }

            $details[$labels[$key]] = filled($value) ? (string) $value : '-';
        }

        return $details;
    }

    public function resultLabel(): string
    {
        return match (true) {
            $this->status_code === 403 => 'ڕێگەپێنەدراو',
            $this->status_code === 404 => 'نەدۆزرایەوە',
            $this->status_code === 422 => 'زانیاری نادروست',
            $this->status_code >= 500 => 'هەڵەی سیستەم',
            $this->status_code >= 400 => 'سەرنەکەوتوو',
            default => 'سەرکەوتوو',
        };
    }

    public function deviceName(): string
    {
        $agent = $this->user_agent ?? '';
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Microsoft Edge',
            str_contains($agent, 'Chrome/') => 'Google Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'وێبگەڕی نەناسراو',
        };
        $device = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iPhone/iPad',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'ئامێری نەناسراو',
        };

        return $browser.' — '.$device;
    }

    private function statusName(?string $status): string
    {
        return match ($status) {
            'green' => 'هاتووە',
            'yellow' => 'دواخراوە',
            'red' => 'نەهاتووە',
            'pending' => 'چاوەڕوانە',
            default => $status ?: 'نادیار',
        };
    }
}
