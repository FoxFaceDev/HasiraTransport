<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('audit_logs')
            ->whereIn('method', ['HEAD', 'OPTIONS'])
            ->orWhere(function ($query) {
                $query->where('method', 'GET')
                    ->where(function ($query) {
                        $query->whereNull('route_name')
                            ->orWhere('route_name', '!=', 'reports.download');
                    });
            })
            ->delete();
    }

    public function down(): void
    {
        // Removed read-only history cannot be reconstructed safely.
    }
};
