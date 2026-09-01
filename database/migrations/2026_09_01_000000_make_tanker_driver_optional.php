<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tankers', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Driver assignment is intentionally optional. Requiring it again would
        // make trucks created after this migration impossible to roll back safely.
    }
};
