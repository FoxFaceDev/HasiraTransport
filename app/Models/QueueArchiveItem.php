<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueArchiveItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue_archive_id', 'tanker_id', 'sequence_number', 'sequence_owner',
        'sequence_owner_phone', 'plate_number', 'vin', 'truck_type', 'truck_color',
        'status', 'scheduled_date', 'scheduled_time', 'note', 'status_updated_at',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'status_updated_at' => 'datetime',
    ];

    public function archive()
    {
        return $this->belongsTo(QueueArchive::class, 'queue_archive_id');
    }
}
