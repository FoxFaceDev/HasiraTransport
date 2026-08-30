<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
            $table->string('license_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('drivers')->whereNull('phone')->update(['phone' => '']);
        DB::table('drivers')->whereNull('license_number')->update(['license_number' => '']);

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
            $table->string('license_number')->nullable(false)->change();
        });
    }
};
