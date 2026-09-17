<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_archive_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tanker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gatekeeper_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->date('scheduled_date')->nullable();
            $table->string('scheduled_time')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['queue_archive_id', 'status']);
            $table->index(['tanker_id', 'queue_archive_id']);
        });

        // Preserve the live state that existed before status occurrences were introduced.
        DB::table('queues')
            ->whereIn('status', ['green', 'red', 'yellow', 'departed'])
            ->orderBy('id')
            ->each(function ($queue) {
                DB::table('queue_status_events')->insert([
                    'tanker_id' => $queue->tanker_id,
                    'gatekeeper_id' => $queue->gatekeeper_id,
                    'status' => $queue->status,
                    'scheduled_date' => $queue->scheduled_date,
                    'scheduled_time' => $queue->scheduled_time,
                    'occurred_at' => $queue->status_updated_at ?? $queue->updated_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_status_events');
    }
};
