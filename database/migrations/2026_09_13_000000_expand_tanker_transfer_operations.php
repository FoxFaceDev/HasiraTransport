<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tankers', function (Blueprint $table) {
            $table->string('truck_model')->nullable()->after('truck_type');
        });

        Schema::table('tanker_transfers', function (Blueprint $table) {
            $table->string('previous_truck_model')->nullable()->after('previous_truck_type');
            $table->string('new_truck_model')->nullable()->after('new_truck_type');
            $table->string('seller_national_id')->nullable();
            $table->string('seller_security_code')->nullable();
            $table->string('seller_agent')->nullable();
            $table->string('seller_agency_number')->nullable();
            $table->date('seller_document_date')->nullable();
            $table->string('buyer_national_id')->nullable();
            $table->string('buyer_security_code')->nullable();
            $table->string('buyer_agent')->nullable();
            $table->string('buyer_agency_number')->nullable();
            $table->date('buyer_document_date')->nullable();
            $table->string('document_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tanker_transfers', function (Blueprint $table) {
            $table->dropColumn([
                'previous_truck_model',
                'new_truck_model',
                'seller_national_id',
                'seller_security_code',
                'seller_agent',
                'seller_agency_number',
                'seller_document_date',
                'buyer_national_id',
                'buyer_security_code',
                'buyer_agent',
                'buyer_agency_number',
                'buyer_document_date',
                'document_path',
            ]);
        });

        Schema::table('tankers', function (Blueprint $table) {
            $table->dropColumn('truck_model');
        });
    }
};
