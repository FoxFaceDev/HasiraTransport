<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tankers', function (Blueprint $table) {
            $table->string('sequence_owner_phone')->nullable()->after('sequence_owner');
            $table->dropUnique(['vin']);
        });
    }

    public function down(): void
    {
        $duplicateVins = DB::table('tankers')
            ->select('vin')
            ->whereNotNull('vin')
            ->groupBy('vin')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('vin');

        foreach ($duplicateVins as $vin) {
            $duplicateIds = DB::table('tankers')
                ->where('vin', $vin)
                ->orderBy('id')
                ->pluck('id')
                ->skip(1);

            DB::table('tankers')->whereIn('id', $duplicateIds)->update(['vin' => null]);
        }

        Schema::table('tankers', function (Blueprint $table) {
            $table->unique('vin');
            $table->dropColumn('sequence_owner_phone');
        });
    }
};
