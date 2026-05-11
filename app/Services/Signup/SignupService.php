<?php

namespace App\Services\Signup;

use App\Models\CustomerProfile;
use App\Models\FraudFlag;
use App\Models\SignupAttempt;
use App\Models\User;
use App\Models\Role;
use App\Models\AccountType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Services\Referral\ReferralAttributionService;
use App\Services\Fraud\FraudDetectionService;
use App\Services\Account\AccountService;

class SignupService
{
    protected AccountService $accountService;

    public function __construct()
    {
        $this->accountService = app(AccountService::class);
    }

    public function registerCustomer(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $fraud = app(FraudDetectionService::class);

            $bvnHash = isset($data['bvn'])
                ? hash('sha256', preg_replace('/\s+/', '', strtoupper($data['bvn'])))
                : null;

            if ($bvnHash && $fraud->detectDuplicateBvn($bvnHash)) {
                FraudFlag::create([
                    'subject_user_id' => 0,
                    'flag_type' => 'duplicate_bvn',
                    'severity' => 'critical',
                    'status' => 'open',
                    'details' => ['bvn_hash' => $bvnHash],
                    'detected_at' => now(),
                ]);

                throw new \RuntimeException('BVN already exists.');
            }

            $attempt = SignupAttempt::create([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'referral_code' => $data['ref_code'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'device_hash' => $data['device_hash'] ?? null,
                'status' => 'pending',
                'attempted_at' => now(),
            ]);

            if ($fraud->detectFakeSignup($data)) {
                $attempt->update(['status' => 'blocked', 'blocked_reason' => 'Suspicious signup pattern']);
                throw new \RuntimeException('Signup blocked due to suspicious activity.');
            }

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            $user->roles()->attach(Role::where('name', 'customer')->value('id'));

            CustomerProfile::create([
                'user_id' => $user->id,
                'address' => $data['address'] ?? null,
                'dob' => $data['dob'] ?? null,
                'bvn_encrypted' => isset($data['bvn']) ? Crypt::encryptString($data['bvn']) : null,
                'bvn_hash' => $bvnHash,
                'nin' => $data['nin'] ?? null,
                'employment_status' => $data['employment_status'] ?? null,
                'monthly_income' => $data['monthly_income'] ?? null,
                'kyc_status' => 'pending',
            ]);

            // Create default wallet account
            $walletType = AccountType::where('code', 'WAL')->firstOrFail();
            $wallet = $this->accountService->createAccount($user->id, $walletType->id, 'Default Wallet');

            app(ReferralAttributionService::class)->attributeNewCustomer(
                $user,
                $data['ref_code'] ?? null,
                [
                    'created_by_user_id' => $data['created_by_user_id'] ?? null,
                    'ip_address' => $data['ip_address'] ?? null,
                    'user_agent' => $data['user_agent'] ?? null,
                    'device_hash' => $data['device_hash'] ?? null,
                    'campaign_code' => $data['campaign_code'] ?? null,
                ]
            );

            $attempt->update(['status' => 'created']);

            return $user->load(['roles', 'customerProfile', 'accounts']);
        });
    }
}
