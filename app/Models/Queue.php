<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Queue extends Model
{
    use HasFactory;

    protected $fillable = [
        'tanker_id',
        'driver_id',
        'status',
        'status_updated_at',
        'gatekeeper_id',
        'scheduled_date',
        'scheduled_time',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status_updated_at' => 'datetime',
        ];
    }

    public function tanker()
    {
        return $this->belongsTo(Tanker::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function gatekeeper()
    {
        return $this->belongsTo(User::class, 'gatekeeper_id');
    }
}
