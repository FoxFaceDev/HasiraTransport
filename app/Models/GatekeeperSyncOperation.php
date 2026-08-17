<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatekeeperSyncOperation extends Model
{
    protected $fillable = [
        'operation_uuid',
        'user_id',
        'tanker_id',
        'type',
        'client_created_at',
    ];

    protected $casts = [
        'client_created_at' => 'datetime',
    ];
}
