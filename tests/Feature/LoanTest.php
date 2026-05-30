<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanSchedule;
use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\AccountType;
use Spatie\Permission\Models\Role;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;
    protected $admin;
    protected $loanProduct;
    protected $accountType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'customer']);
        Role::create(['name' => 'admin']);

        // Create users
        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Create account types
        $this->accountType = AccountType::firstOrCreate(
            ['code' => 'WAL'],
            ['name' => 'Wallet Account', 'currency' => 'NGN', 'active' => true]
        );
        AccountType::firstOrCreate(
            ['code' => 'LOAN'],
            ['name' => 'Loan Account', 'currency' => 'NGN', 'account_category' => 'LOAN', 'normal_balance' => 'CREDIT', 'supports_deposit' => false, 'supports_withdrawal' => false, 'supports_transfer' => false, 'is_customer_visible' => true, 'requires_kyc' => true, 'active' => true]
        );
        $this->loanProduct = LoanProduct::factory()->create([
            'min_amount' => 1000,
            'max_amount' => 50000,
            'interest_rate' => 12.5,
            'processing_fee' => 2.5, // 2.5% processing fee
            'requires_account' => false,
        ]);

        // Create default wallet for customer
        $accountService = app(\App\Services\Account\AccountService::class);
        $accountService->createAccount($this->customer->id, $this->accountType->id, 'Default Wallet');
    }

    public function test_customer_can_apply_for_loan()
    {
        // Login to get token
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $this->customer->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('data.access_token');

        $loanData = [
            'customer_id' => $this->customer->id,
            'loan_product_id' => $this->loanProduct->id,
            'requested_amount' => 5000,
            'requested_tenure' => 12,
            'repayment_plan' => 'monthly',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/loan-applications', $loanData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'customer_id',
                        'requested_amount',
                        'requested_tenure',
                        'status'
                    ]
                ]);
    }

    public function test_customer_can_create_multiple_active_loan_accounts()
    {
        $loanType = AccountType::where('code', 'LOAN')->first();
        $accountService = app(\App\Services\Account\AccountService::class);

        $firstLoanAccount = $accountService->createAccount($this->customer->id, $loanType->id, 'First Loan Account');
        $secondLoanAccount = $accountService->createAccount($this->customer->id, $loanType->id, 'Second Loan Account');

        $this->assertNotNull($firstLoanAccount->id);
        $this->assertNotNull($secondLoanAccount->id);
        $this->assertNotEquals($firstLoanAccount->id, $secondLoanAccount->id);
        $this->assertDatabaseHas('accounts', [
            'id' => $firstLoanAccount->id,
            'customer_id' => $this->customer->id,
            'account_type_id' => $loanType->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('accounts', [
            'id' => $secondLoanAccount->id,
            'customer_id' => $this->customer->id,
            'account_type_id' => $loanType->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_approve_loan()
    {
        // First create a loan application
        $application = \App\Models\LoanApplication::factory()->create([
            'customer_id' => $this->customer->id,
            'loan_product_id' => $this->loanProduct->id,
            'requested_amount' => 5000,
            'requested_tenure' => 12,
               'status' => 'submitted',
        ]);

        // Login as admin to get token
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('data.access_token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/loan-applications/{$application->id}/approve");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Loan application approved successfully'
                ]);
    }

    public function test_admin_can_disburse_approved_loan()
    {
        // Create default wallet for customer
        $accountService = app(\App\Services\Account\AccountService::class);
        $wallet = $accountService->createAccount($this->customer->id, $this->accountType->id, 'Default Wallet');

        // First create a loan application and approve it to create the loan
        $application = \App\Models\LoanApplication::factory()->create([
            'customer_id' => $this->customer->id,
            'loan_product_id' => $this->loanProduct->id,
            'account_id' => null,
            'requested_amount' => 5000,
            'requested_tenure' => 12,
            'status' => 'submitted',
        ]);

        // Approve the application (this creates the loan)
        $loanService = app(\App\Services\Loan\LoanService::class);
        $loan = $loanService->approveApplication($application->id);

        // Login as admin to get token
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('data.access_token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/loans/{$loan->id}/disburse");

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Loan disbursed'
                ]);

        $loan = $loan->fresh();

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'status' => 'active',
        ]);

        // Check that wallet balance was updated (net disbursement after processing fee)
        $expectedNetAmount = 5000 - (5000 * 0.025); // 2.5% processing fee
        $walletAccountId = Account::where('customer_id', $this->customer->id)
            ->whereHas('accountType', function ($q) {
                $q->where('code', 'WAL');
            })->first()->id;

        $this->assertDatabaseHas('account_balances', [
            'account_id' => $walletAccountId,
            'available_balance' => $expectedNetAmount,
        ]);

        $schedule = LoanSchedule::where('loan_id', $loan->id)
            ->orderBy('installment_no')
            ->first();

        $this->assertNotNull($schedule);
        $this->assertEquals($loan->first_payment_date->toDateString(), $schedule->due_date->toDateString());
    }

    public function test_customer_can_make_loan_repayment_with_default_wallet_deduction()
    {
        $walletType = AccountType::firstOrCreate(
            ['code' => 'WAL'],
            ['name' => 'Wallet Account', 'currency' => 'NGN', 'active' => true]
        );
        $wallet = Account::factory()->create([
            'customer_id' => $this->customer->id,
            'account_type_id' => $walletType->id,
            'status' => 'active',
        ]);

        AccountBalance::factory()->create([
            'account_id' => $wallet->id,
            'available_balance' => 1000,
            'ledger_balance' => 1000,
        ]);

        $loan = Loan::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'outstanding_total' => 500,
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $this->customer->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('data.access_token');

        $repaymentData = [
            'amount' => 500,
            'payment_channel' => 'wallet',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/user/loans/{$loan->id}/repay", $repaymentData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Repayment recorded'
                ]);

        $this->assertDatabaseHas('account_balances', [
            'account_id' => $wallet->id,
            'available_balance' => 500,
        ]);
    }

    public function test_customer_can_payoff_loan_using_default_wallet()
    {
        $walletType = AccountType::firstOrCreate(
            ['code' => 'WAL'],
            ['name' => 'Wallet Account', 'currency' => 'NGN', 'active' => true]
        );
        $wallet = Account::factory()->create([
            'customer_id' => $this->customer->id,
            'account_type_id' => $walletType->id,
            'status' => 'active',
        ]);

        AccountBalance::factory()->create([
            'account_id' => $wallet->id,
            'available_balance' => 1200,
            'ledger_balance' => 1200,
        ]);

        $loan = Loan::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'outstanding_total' => 1000,
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $this->customer->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('data.access_token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/user/loans/{$loan->id}/payoff", []);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Loan payoff processed'
                ]);

        $this->assertDatabaseHas('account_balances', [
            'account_id' => $wallet->id,
            'available_balance' => 200,
        ]);
    }

    public function test_customer_can_list_their_loans()
    {
        $loan = Loan::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'outstanding_total' => 1500,
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $this->customer->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('data.access_token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/user/loans');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        ['id', 'customer_id', 'status', 'outstanding_total']
                    ]
                ])
                ->assertJsonPath('data.0.id', $loan->id);
    }

    public function test_customer_cannot_apply_for_loan_with_invalid_amount()
    {
        // Login as customer to get token
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $this->customer->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('data.access_token');

        $loanData = [
            'customer_id' => $this->customer->id,
            'loan_product_id' => $this->loanProduct->id,
            'requested_amount' => 100000, // Exceeds max_amount
            'requested_tenure' => 12,
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/loan-applications', $loanData);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors'
                ]);
    }
}