<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tanker_transfers', function (Blueprint $table) {
            $table->string('document_number', 100)->nullable()->after('change_type');
        });
    }

    public function down(): void
    {
        Schema::table('tanker_transfers', function (Blueprint $table) {
            $table->dropColumn('document_number');
        });
    }
};
