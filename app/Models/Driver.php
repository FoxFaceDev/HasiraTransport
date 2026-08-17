<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'license_number',
        'has_certificate',
        'certificate_number',
    ];

    protected $casts = [
        'has_certificate' => 'boolean',
    ];

    public function tankers()
    {
        return $this->hasMany(Tanker::class);
    }

    public function queues()
    {
        return $this->hasMany(Queue::class);
    }
}
