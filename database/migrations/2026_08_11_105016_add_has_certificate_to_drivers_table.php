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
        if (Schema::hasColumn('drivers', 'has_certificate')) {
            return;
        }

        Schema::table('drivers', function (Blueprint $table) {
            $table->boolean('has_certificate')->default(false)->after('license_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The original drivers migration now owns this column.
    }
};
