<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('certificate_number')->index();
        });

        Schema::table('tankers', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('driver_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tankers', function (Blueprint $table) {
            $table->dropColumn('blocked_at');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('blocked_at');
        });
    }
};
