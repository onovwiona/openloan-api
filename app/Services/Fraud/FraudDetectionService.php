<?php

namespace App\Services\Fraud;
use App\Models\FraudFlag;
use App\Models\CustomerProfile;
use App\Models\SignupAttempt;
use App\Models\User;

class FraudDetectionService
{
    public static function flagSelfReferral(User $subject, User $related, array $meta = []): FraudFlag
    {
        return FraudFlag::create([
            'subject_user_id' => $subject->id,
            'related_user_id' => $related->id,
            'flag_type' => 'self_referral',
            'severity' => 'high',
            'status' => 'open',
            'details' => $meta,
            'detected_at' => now(),
        ]);
    }

    public static function flagCircularReferral(int $referrerId, int $referredId): FraudFlag
    {
        return FraudFlag::create([
            'subject_user_id' => $referrerId,
            'related_user_id' => $referredId,
            'flag_type' => 'circular_referral',
            'severity' => 'critical',
            'status' => 'open',
            'details' => [
                'referrer_user_id' => $referrerId,
                'referred_user_id' => $referredId,
            ],
            'detected_at' => now(),
        ]);
    }

    public function detectDuplicateBvn(string $bvnHash): bool
    {
        return CustomerProfile::where('bvn_hash', $bvnHash)->exists();
    }

    public function detectFakeSignup(array $payload): bool
    {
        $deviceHash = $payload['device_hash'] ?? null;
        $ip = $payload['ip_address'] ?? null;

        if (! $deviceHash && ! $ip) {
            return false;
        }

        return SignupAttempt::where(function ($q) use ($deviceHash, $ip) {
            if ($deviceHash) {
                $q->where('device_hash', $deviceHash);
            }
            if ($ip) {
                $q->orWhere('ip_address', $ip);
            }
        })->count() > 5;
    }
}
