<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tanker extends Model
{
    use HasFactory;

    protected $fillable = ['plate_number', 'driver_id', 'sequence_number', 'sequence_owner', 'sequence_owner_phone', 'vin', 'truck_type', 'truck_color'];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function queues()
    {
        return $this->hasMany(Queue::class);
    }

    public function latestQueue()
    {
        return $this->hasOne(Queue::class)->latestOfMany();
    }
}
