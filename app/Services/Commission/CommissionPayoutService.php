<?php

namespace App\Services\Commission;

use App\Models\CommissionEvent;
use App\Models\CommissionPayoutBatch;
use App\Models\CommissionPayoutItem;
use App\Models\CommissionRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class CommissionPayoutService
{
    public function createBatchForApprovedEvents(Collection $events, User $createdBy): CommissionPayoutBatch
    {
        return DB::transaction(function () use ($events, $createdBy) {
            $batch = CommissionPayoutBatch::create([
                'batch_no' => 'BATCH-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5)),
                'status' => 'draft',
                'total_amount' => 0,
                'created_by' => $createdBy->id,
            ]);

            $total = 0;

            foreach ($events as $event) {
                if ($event->status !== 'approved') {
                    continue;
                }

                CommissionPayoutItem::create([
                    'payout_batch_id' => $batch->id,
                    'commission_event_id' => $event->id,
                    'beneficiary_user_id' => $event->beneficiary_user_id,
                    'amount' => $event->commission_amount,
                    'status' => 'queued',
                ]);

                $total += $event->commission_amount;
            }

            $batch->update([
                'total_amount' => $total,
                'status' => 'processing',
                'processed_at' => now(),
            ]);

            return $batch;
        });
    }
}
