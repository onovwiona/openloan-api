<?php

namespace App\Services\Commission;

use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function createCommissionEvent(string $triggerType, User $beneficiary, ?User $sourceUser, $reference, ?float $baseAmount = null): CommissionEvent
    {
        return DB::transaction(function () use ($triggerType, $beneficiary, $sourceUser, $reference, $baseAmount) {
            $rule = CommissionRule::where('trigger_type', $triggerType)
                ->where('beneficiary_role', $this->resolveBeneficiaryRole($beneficiary))
                ->where('is_active', true)
                ->first();

            if (! $rule) {
                throw new \RuntimeException('No active commission rule found.');
            }

            if ($baseAmount !== null && $rule->minimum_amount !== null && $baseAmount < $rule->minimum_amount) {
                throw new \RuntimeException('Base amount below commission threshold.');
            }

            $commissionAmount = $this->calculateCommission($rule, $baseAmount);

            return CommissionEvent::create([
                'rule_id' => $rule->id,
                'beneficiary_user_id' => $beneficiary->id,
                'source_user_id' => $sourceUser?->id,
                'event_type' => $triggerType,
                'reference_type' => is_object($reference) ? class_basename($reference) : null,
                'reference_id' => is_object($reference) ? $reference->id : null,
                'base_amount' => $baseAmount,
                'commission_amount' => $commissionAmount,
                'status' => 'pending',
                'earned_at' => now(),
            ]);
        });
    }

    private function calculateCommission(CommissionRule $rule, ?float $baseAmount): float
    {
        if ($rule->amount_type === 'fixed') {
            return (float) $rule->amount_value;
        }

        if ($baseAmount === null) {
            throw new \RuntimeException('Base amount is required for percentage commission.');
        }

        return round(($baseAmount * $rule->amount_value) / 100, 2);
    }

    private function resolveBeneficiaryRole(User $user): string
    {
        return $user->roles()->first()?->name ?? 'customer';
    }
}
