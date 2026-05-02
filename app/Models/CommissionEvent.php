<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\User;
use App\Models\CommissionRule;
use App\Models\ReferralEdge;

class CommissionEvent extends Model
{
    use HasFactory;
    protected $fillable = [
        'rule_id',
        'beneficiary_user_id',
        'source_user_id',
        'event_type',
        'reference_type',
        'reference_id',
        'base_amount',
        'commission_amount',
        'status',
        'earned_at',
        'approved_at',
        'paid_at'
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'base_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2'
    ];

    public function beneficiary()
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    public function sourceUser()
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function rule()
    {
        return $this->belongsTo(CommissionRule::class, 'rule_id');
    }

    public function referralEdge()
    {
        return $this->belongsTo(ReferralEdge::class, 'reference_id')
            ->where('reference_type', 'ReferralEdge');
    }
}
