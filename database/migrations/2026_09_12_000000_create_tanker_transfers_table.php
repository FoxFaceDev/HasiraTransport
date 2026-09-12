<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanker_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tanker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_type')->default('sale');
            $table->date('transferred_at');
            $table->string('previous_owner')->nullable();
            $table->string('previous_owner_phone')->nullable();
            $table->string('previous_plate_number');
            $table->string('previous_vin')->nullable();
            $table->string('previous_truck_type');
            $table->string('previous_truck_color')->nullable();
            $table->string('new_owner')->nullable();
            $table->string('new_owner_phone')->nullable();
            $table->string('new_plate_number');
            $table->string('new_vin')->nullable();
            $table->string('new_truck_type');
            $table->string('new_truck_color')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tanker_id', 'transferred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanker_transfers');
    }
};
