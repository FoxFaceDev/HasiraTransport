<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tankers', function (Blueprint $table) {
            if (!Schema::hasColumn('tankers', 'sequence_number')) {
                $table->string('sequence_number')->after('id');
            }
            if (!Schema::hasColumn('tankers', 'truck_type')) {
                $table->string('truck_type')->after('plate_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tankers', function (Blueprint $table) {
            $table->dropColumn(['sequence_number', 'truck_type']);
        });
    }
};
