<?php

namespace App\Services\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralEdge;
use App\Models\ReferralPath;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\Fraud\FraudDetectionService;

class ReferralTreeService
{
    public function attachReferral(User $referrer, User $referred, ?ReferralCode $referralCode = null): void
    {
        DB::transaction(function () use ($referrer, $referred, $referralCode) {
            $this->ensureNoCircularReference($referrer->id, $referred->id);

            ReferralEdge::create([
                'referrer_user_id' => $referrer->id,
                'referred_user_id' => $referred->id,
                'referral_code_id' => $referralCode?->id,
            ]);

            $this->buildReferralPaths($referrer->id, $referred->id);
        });
    }

    private function ensureNoCircularReference(int $referrerId, int $referredId): void
    {
        $circleExists = ReferralPath::where('ancestor_user_id', $referredId)
            ->where('descendant_user_id', $referrerId)
            ->exists();

        if ($circleExists || $referrerId === $referredId) {
            FraudDetectionService::flagCircularReferral($referrerId, $referredId);
            throw new \RuntimeException('Circular referral detected.');
        }
    }

    private function buildReferralPaths(int $referrerId, int $referredId): void
    {
        ReferralPath::create([
            'ancestor_user_id' => $referrerId,
            'descendant_user_id' => $referredId,
            'depth' => 1,
        ]);

        $ancestorPaths = ReferralPath::where('descendant_user_id', $referrerId)->get();

        foreach ($ancestorPaths as $path) {
            ReferralPath::firstOrCreate([
                'ancestor_user_id' => $path->ancestor_user_id,
                'descendant_user_id' => $referredId,
            ], [
                'depth' => $path->depth + 1,
            ]);
        }
    }
}
