<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            $table->string('shift_time')->nullable()->after('scheduled_time');
        });

        Schema::table('queue_status_events', function (Blueprint $table) {
            $table->string('shift_time')->nullable()->after('scheduled_time');
        });

        Schema::table('queue_archive_items', function (Blueprint $table) {
            $table->string('shift_time')->nullable()->after('scheduled_time');
        });

        DB::table('queue_status_events')
            ->whereIn('status', ['green', 'yellow'])
            ->where(function ($query) {
                $query->where('scheduled_time', 'like', '5:30%')
                    ->orWhere('scheduled_time', 'like', '12:00%');
            })
            ->update(['shift_time' => DB::raw('scheduled_time')]);

        DB::table('queue_status_events')
            ->where('status', 'departed')
            ->orderBy('id')
            ->each(function ($event) {
                $shiftTime = DB::table('queue_status_events')
                    ->where('tanker_id', $event->tanker_id)
                    ->where('id', '<', $event->id)
                    ->whereNull('cancelled_at')
                    ->whereIn('status', ['green', 'yellow'])
                    ->where(function ($query) {
                        $query->where('scheduled_time', 'like', '5:30%')
                            ->orWhere('scheduled_time', 'like', '12:00%');
                    })
                    ->latest('id')
                    ->value('scheduled_time');

                if ($shiftTime) {
                    DB::table('queue_status_events')->where('id', $event->id)->update(['shift_time' => $shiftTime]);
                }
            });

        DB::table('queues')->orderBy('id')->each(function ($queue) {
            $shiftTime = null;

            if (in_array($queue->status, ['green', 'yellow'], true)
                && (str_starts_with((string) $queue->scheduled_time, '5:30')
                    || str_starts_with((string) $queue->scheduled_time, '12:00'))) {
                $shiftTime = $queue->scheduled_time;
            } elseif ($queue->status === 'departed') {
                $shiftTime = DB::table('queue_status_events')
                    ->where('tanker_id', $queue->tanker_id)
                    ->whereNull('cancelled_at')
                    ->where('status', 'departed')
                    ->latest('id')
                    ->value('shift_time');
            }

            if ($shiftTime) {
                DB::table('queues')->where('id', $queue->id)->update(['shift_time' => $shiftTime]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('queue_archive_items', function (Blueprint $table) {
            $table->dropColumn('shift_time');
        });

        Schema::table('queue_status_events', function (Blueprint $table) {
            $table->dropColumn('shift_time');
        });

        Schema::table('queues', function (Blueprint $table) {
            $table->dropColumn('shift_time');
        });
    }
};
