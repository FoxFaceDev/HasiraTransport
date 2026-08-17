<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tankers', function (Blueprint $table) {
            $table->string('sequence_owner')->nullable()->after('sequence_number');
            $table->string('vin')->nullable()->unique()->after('plate_number');
            $table->string('truck_color')->nullable()->after('truck_type');
        });
    }

    public function down(): void
    {
        Schema::table('tankers', function (Blueprint $table) {
            $table->dropUnique(['vin']);
            $table->dropColumn(['sequence_owner', 'vin', 'truck_color']);
        });
    }
};
