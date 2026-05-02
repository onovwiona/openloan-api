<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionPayoutBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_no',
        'total_amount',
        'total_items',
        'status',
        'processed_by',
        'paid_at'
    ];

    public function items()
    {
        return $this->hasMany(CommissionPayoutItem::class, 'batch_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}

