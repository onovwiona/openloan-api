<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommissionPayoutItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'user_id',
        'amount',
        'bank_name',
        'account_name',
        'account_number',
        'status',
        'paid_at'
    ];

    public function batch()
    {
        return $this->belongsTo(CommissionPayoutBatch::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
