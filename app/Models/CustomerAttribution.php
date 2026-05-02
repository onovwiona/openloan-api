<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAttribution extends Model
{
    use HasFactory;
    protected $fillable = [
        'customer_user_id',
        'source_type',
        'source_user_id',
        'referral_code_id',
        'created_by_user_id',
        'campaign_code',
        'ip_address',
        'user_agent',
        'device_hash',
        'status',
        'notes',
        'captured_at'
    ];

    protected $casts = [
        'captured_at' => 'datetime'
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function sourceUser()
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function referralCode()
    {
        return $this->belongsTo(ReferralCode::class);
    }
}
