<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueArchive extends Model
{
    use HasFactory;

    protected $fillable = ['reset_by', 'reset_at', 'report_file'];

    protected $casts = ['reset_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(QueueArchiveItem::class);
    }

    public function resetter()
    {
        return $this->belongsTo(User::class, 'reset_by');
    }
}
