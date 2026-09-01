<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PermissionCatalog
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'شۆفێرەکان' => [
                'view drivers' => 'بینینی شۆفێرەکان',
                'create drivers' => 'زیادکردنی شۆفێر',
                'edit drivers' => 'دەستکاریکردنی شۆفێر',
                'delete drivers' => 'سڕینەوەی شۆفێر',
            ],
            'تەنکەرەکان' => [
                'view tankers' => 'بینینی تەنکەرەکان',
                'create tankers' => 'زیادکردنی تەنکەر',
                'edit tankers' => 'دەستکاریکردنی تەنکەر',
                'delete tankers' => 'سڕینەوەی تەنکەر',
                'manage tanker settings' => 'بەڕێوەبردنی ڕێکخستنەکانی تەنکەر',
            ],
            'کۆنترۆڵی دەروازە' => [
                'view gatekeeper' => 'بینینی کۆنترۆڵی دەروازە',
                'update queue status' => 'گۆڕینی دۆخی تەنکەر',
                'update queue notes' => 'نووسین و گۆڕینی تێبینی',
                'reset queue' => 'سفرکردنەوەی لیست',
            ],
            'ڕاپۆرتەکان' => [
                'view reports' => 'بینینی ڕاپۆرتەکان',
                'download reports' => 'کردنەوە و داگرتنی ڕاپۆرت',
            ],
            'بەکارهێنەران و ڕۆڵەکان' => [
                'manage users' => 'بەڕێوەبردنی بەکارهێنەران',
                'manage roles' => 'بەڕێوەبردنی ڕۆڵ و دەسەڵاتەکان',
            ],
            'چاودێری سیستەم' => [
                'view audit logs' => 'بینینی تۆماری چاودێری',
            ],
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public static function names(): Collection
    {
        return collect(self::groups())->flatMap(fn (array $permissions) => array_keys($permissions))->values();
    }
}
