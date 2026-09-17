<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueStatusEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue_archive_id',
        'tanker_id',
        'gatekeeper_id',
        'status',
        'scheduled_date',
        'scheduled_time',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date:Y-m-d',
            'occurred_at' => 'datetime',
        ];
    }

    public function tanker()
    {
        return $this->belongsTo(Tanker::class);
    }

    public function gatekeeper()
    {
        return $this->belongsTo(User::class, 'gatekeeper_id');
    }

    public function archive()
    {
        return $this->belongsTo(QueueArchive::class, 'queue_archive_id');
    }
}
