<?php

namespace App\Services\Referral;

use App\Models\CustomerAttribution;
use App\Models\ReferralCode;
use App\Services\Fraud\FraudDetectionService;
use App\Models\User;
use App\Services\Referral\ReferralTreeService as ReferralReferralTreeService;
use Illuminate\Support\Facades\DB;


class ReferralAttributionService
{
    public function attributeNewCustomer(User $customer, ?string $refCode, array $meta = []): CustomerAttribution
    {
        return DB::transaction(function () use ($customer, $refCode, $meta) {
            $referralCode = null;
            $referrer = null;
            $sourceType = 'organic';

            if ($refCode) {
                $referralCode = ReferralCode::where('code', $refCode)
                    ->where('is_active', true)
                    ->first();

                if (! $referralCode) {
                    return CustomerAttribution::create([
                        'customer_user_id' => $customer->id,
                        'source_type' => 'organic',
                        'status' => 'rejected',
                        'notes' => 'Invalid referral code',
                        ...$meta,
                    ]);
                }

                $referrer = $referralCode->user;

                if ($referrer->id === $customer->id) {
                    FraudDetectionService::flagSelfReferral($customer, $referrer, $meta);
                    throw new \RuntimeException('Self referral is not allowed.');
                }

                $sourceType = $this->determineSourceType($referralCode->code_type);
            }

            $attribution = CustomerAttribution::create([
                'customer_user_id' => $customer->id,
                'source_type' => $sourceType,
                'source_user_id' => $referrer?->id,
                'referral_code_id' => $referralCode?->id,
                'created_by_user_id' => $meta['created_by_user_id'] ?? null,
                'campaign_code' => $meta['campaign_code'] ?? null,
                'ip_address' => $meta['ip_address'] ?? null,
                'user_agent' => $meta['user_agent'] ?? null,
                'device_hash' => $meta['device_hash'] ?? null,
                'status' => 'verified',
                'captured_at' => now(),
            ]);

            if ($referrer && $sourceType === 'customer_referral') {
                app(ReferralReferralTreeService::class)->attachReferral($referrer, $customer, $referralCode);
            }

            return $attribution;
        });
    }

    private function determineSourceType(string $codeType): string
    {
        return match ($codeType) {
            'staff' => 'staff',
            'marketer' => 'marketer',
            'customer' => 'customer_referral',
            'campaign' => 'campaign',
            default => 'organic',
        };
    }
}
