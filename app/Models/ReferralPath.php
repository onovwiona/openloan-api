<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;



class ReferralPath extends Model
{
        use HasFactory;
    protected $fillable = [
        'ancestor_user_id',
        'descendant_user_id',
        'depth'
    ];
}
