<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_archive_items', function (Blueprint $table) {
            $table->string('truck_model')->nullable()->after('truck_type');
        });

        $this->splitCombinedValues('tankers', 'truck_type', 'truck_model');
        $this->splitCombinedValues('tanker_transfers', 'previous_truck_type', 'previous_truck_model');
        $this->splitCombinedValues('tanker_transfers', 'new_truck_type', 'new_truck_model');
        $this->splitCombinedValues('queue_archive_items', 'truck_type', 'truck_model');
    }

    public function down(): void
    {
        DB::table('queue_archive_items')
            ->whereNotNull('truck_model')
            ->orderBy('id')
            ->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    DB::table('queue_archive_items')->where('id', $item->id)->update([
                        'truck_type' => trim($item->truck_type.' '.$item->truck_model),
                    ]);
                }
            });

        Schema::table('queue_archive_items', function (Blueprint $table) {
            $table->dropColumn('truck_model');
        });
    }

    private function splitCombinedValues(string $table, string $typeColumn, string $modelColumn): void
    {
        DB::table($table)
            ->select(['id', $typeColumn, $modelColumn])
            ->where(function ($query) use ($modelColumn): void {
                $query->whereNull($modelColumn)->orWhere($modelColumn, '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($items) use ($table, $typeColumn, $modelColumn): void {
                foreach ($items as $item) {
                    $combined = trim((string) $item->{$typeColumn});

                    if (! preg_match('/^(.+?)\s+((?:19|20)\d{2})$/u', $combined, $matches)) {
                        continue;
                    }

                    DB::table($table)->where('id', $item->id)->update([
                        $typeColumn => trim($matches[1]),
                        $modelColumn => $matches[2],
                    ]);
                }
            });
    }
};
