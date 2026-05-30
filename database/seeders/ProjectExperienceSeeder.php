<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\CommissionRule;
use App\Models\FraudFlag;
use App\Models\LoanProduct;
use App\Models\ReferralCode;
use App\Models\SignupAttempt;
use App\Models\User;
use App\Services\Account\AccountService;
use App\Services\Commission\CommissionService;
use App\Services\Loan\LoanService;
use App\Services\Referral\ReferralTreeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class ProjectExperienceSeeder extends Seeder
{
    public function run(): void
    {
        Auth::logout();

        $admin = $this->createUser(
            'Super',
            'Admin',
            'admin@opendoor.com',
            '+2348000000100',
            ['admin']
        );

        $marketer = $this->createUser(
            'John',
            'Marketer',
            'john.marketer@opendoor.com',
            '+2348000000101',
            ['marketer']
        );

        $staff = $this->createUser(
            'Jane',
            'Staff',
            'jane.staff@opendoor.com',
            '+2348000000102',
            ['staff']
        );

        $auditor = $this->createUser(
            'Amy',
            'Auditor',
            'amy.auditor@opendoor.com',
            '+2348000000103',
            ['auditor']
        );

        $customers = collect([
            ['first_name' => 'Alice', 'last_name' => 'Customer1', 'email' => 'alice.customer1@example.com', 'phone' => '+2348000000111'],
            ['first_name' => 'Bob', 'last_name' => 'Customer2', 'email' => 'bob.customer2@example.com', 'phone' => '+2348000000112'],
            ['first_name' => 'Charlie', 'last_name' => 'Customer3', 'email' => 'charlie.customer3@example.com', 'phone' => '+2348000000113'],
            ['first_name' => 'Diana', 'last_name' => 'Customer4', 'email' => 'diana.customer4@example.com', 'phone' => '+2348000000114'],
            ['first_name' => 'Eve', 'last_name' => 'Organic', 'email' => 'eve.organic@example.com', 'phone' => '+2348000000115'],
        ])->map(function ($data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'password' => bcrypt('password'),
                    'is_active' => true,
                ])
            );
            $user->syncRoles(['customer']);
            return $user;
        });

        $savingsType = AccountType::where('code', 'SAV')->first();
        $loanRepayType = AccountType::where('code', 'LOAN')->first();

        $accountService = app(AccountService::class);
        $loanService = app(LoanService::class);
        $commissionService = app(CommissionService::class);
        $referralService = app(ReferralTreeService::class);

        if (! $savingsType || ! $loanRepayType) {
            return;
        }

        $accounts = $customers->mapWithKeys(function (User $customer) use ($accountService, $savingsType, $loanRepayType) {
            $savingsAccount = $accountService->createAccount($customer->id, $savingsType->id, "{$customer->first_name} Savings");
            $loanAccount = $accountService->createAccount($customer->id, $loanRepayType->id, "{$customer->first_name} Loan");

            $accountService->credit($savingsAccount->id, 150000, 'Initial funding', 'seed', null);
            $accountService->credit($loanAccount->id, 50000, 'Loan reserve', 'seed', null, true);

            return [
                $customer->id => [
                    'savings' => $savingsAccount,
                    'loan' => $loanAccount,
                ],
            ];
        });

        $referralCode = ReferralCode::firstOrCreate(
            ['user_id' => $marketer->id],
            [
                'code' => 'MARKETER2026',
                'code_type' => 'marketer',
                'is_active' => true,
                'issued_at' => now(),
                'expires_at' => now()->addMonths(12),
            ]
        );

        $customers->take(3)->each(function (User $customer) use ($marketer, $referralService, $referralCode) {
            try {
                $referralService->attachReferral($marketer, $customer, $referralCode);
            } catch (\Throwable $e) {
                // ignore duplicates in seed runs
            }
        });

// Commission rules are now seeded by CommissionRuleSeeder - no duplicates needed here

        Auth::login($admin);

        $personalProduct = LoanProduct::where('code', 'PERSONAL')->first();
        if ($personalProduct) {
            $application1 = $loanService->createApplication(
                $customers[0]->id,
                $personalProduct->id,
                120000,
                12,
                $accounts[$customers[0]->id]['loan']->id,
                'Small home repair',
                180000,
                'employed'
            );
            $loanService->submitApplication($application1->id);
            $approvedLoan = $loanService->approveApplication($application1->id);
            $loanService->disburseLoan($approvedLoan->id, $accounts[$customers[0]->id]['savings']->id);

            $repayment = $loanService->recordRepayment(
                $approvedLoan->id,
                30000,
                $accounts[$customers[0]->id]['loan']->id,
                'bank',
                'RFD-001'
            );
            $loanService->allocateRepayment($repayment->id);

            $commissionService->createCommissionEvent(
                'referral_bonus',
                $marketer,
                $customers[0],
                $approvedLoan,
                120000
            );
        }

        $pendingApplication = $loanService->createApplication(
            $customers[1]->id,
            $personalProduct->id,
            80000,
            6,
            $accounts[$customers[1]->id]['loan']->id,
            'Education',
            120000,
            'self-employed'
        );
        $loanService->submitApplication($pendingApplication->id);
        $loanService->rejectApplication($pendingApplication->id, 'Insufficient income documentation');

        $loanApplication2 = $loanService->createApplication(
            $customers[2]->id,
            $personalProduct->id,
            50000,
            6,
            $accounts[$customers[2]->id]['loan']->id,
            'Mobile device purchase',
            90000,
            'salaried'
        );
        $loanService->submitApplication($loanApplication2->id);

        $commissionService->createCommissionEvent(
            'signup',
            $marketer,
            $customers[2],
            $referralCode,
            0
        );

        SignupAttempt::factory()->create([
            'user_id' => $customers[0]->id,
            'phone' => $customers[0]->phone,
            'email' => $customers[0]->email,
            'ip_address' => '203.0.113.45',
            'user_agent' => 'PostmanRuntime/7.29.2',
            'status' => 'failed',
            'failure_reason' => 'Invalid password',
        ]);

        SignupAttempt::factory()->create([
            'phone' => '+2348000000999',
            'email' => 'unknown@example.com',
            'ip_address' => '203.0.113.45',
            'user_agent' => 'Mozilla/5.0',
            'status' => 'failed',
            'failure_reason' => 'User not found',
        ]);

        SignupAttempt::factory()->create([
            'user_id' => $customers[3]->id,
            'phone' => $customers[3]->phone,
            'email' => $customers[3]->email,
            'ip_address' => '198.51.100.27',
            'user_agent' => 'Mozilla/5.0',
            'status' => 'pending',
        ]);

FraudFlag::updateOrCreate(
            [
                'subject_user_id' => $customers[1]->id,
                'flag_type' => 'ip_spam',
            ],
            [
                'related_user_id' => $marketer->id,
                'severity' => 'high',
                'status' => 'open',
                'details' => ['ip_address' => '203.0.113.45', 'attempts' => 2],
                'detected_by' => $auditor->id,
                'detected_at' => now(),
            ]
        );

FraudFlag::updateOrCreate(
            [
                'subject_user_id' => $customers[4]->id,
                'flag_type' => 'duplicate_bvn',
            ],
            [
                'related_user_id' => $marketer->id,
                'severity' => 'medium',
                'status' => 'reviewing',
                'details' => ['bvn' => '12345678901'],
                'detected_by' => $auditor->id,
                'detected_at' => now()->subDay(),
                'reviewed_by' => $auditor->id,
                'reviewed_at' => now(),
            ]
        );

        Auth::logout();
    }

    protected function createUser(string $firstName, string $lastName, string $email, string $phone, array $roles): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );

        $user->syncRoles($roles);
        return $user;
    }
}
