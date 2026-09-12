<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reset_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reset_at')->index();
            $table->string('report_file')->nullable();
            $table->timestamps();
        });

        Schema::create('queue_archive_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_archive_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tanker_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sequence_number');
            $table->string('sequence_owner')->nullable();
            $table->string('sequence_owner_phone')->nullable();
            $table->string('plate_number');
            $table->string('vin')->nullable();
            $table->string('truck_type');
            $table->string('truck_color')->nullable();
            $table->string('status')->default('pending');
            $table->date('scheduled_date')->nullable();
            $table->string('scheduled_time')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamps();

            $table->index(['queue_archive_id', 'status']);
            $table->index(['tanker_id', 'queue_archive_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_archive_items');
        Schema::dropIfExists('queue_archives');
    }
};
