<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TankerTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tanker_id', 'recorded_by', 'change_type', 'document_number', 'transferred_at',
        'previous_owner', 'previous_owner_phone', 'previous_plate_number',
        'previous_vin', 'previous_truck_type', 'previous_truck_model', 'previous_truck_color',
        'new_owner', 'new_owner_phone', 'new_plate_number', 'new_vin',
        'new_truck_type', 'new_truck_model', 'new_truck_color',
        'seller_national_id', 'seller_security_code', 'seller_agent',
        'seller_agency_number', 'seller_document_date', 'buyer_national_id',
        'buyer_security_code', 'buyer_agent', 'buyer_agency_number',
        'buyer_document_date', 'document_path', 'note',
    ];

    protected $casts = [
        'transferred_at' => 'date',
        'seller_document_date' => 'date',
        'buyer_document_date' => 'date',
    ];

    public function tanker()
    {
        return $this->belongsTo(Tanker::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
