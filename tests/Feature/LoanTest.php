<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Account;
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

        // Create account type and product
        $this->accountType = AccountType::factory()->create();
        $this->loanProduct = LoanProduct::factory()->create([
            'min_amount' => 1000,
            'max_amount' => 50000,
            'interest_rate' => 12.5,
        ]);
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

    public function test_admin_can_approve_loan()
    {
        // First create a loan application
        $application = \App\Models\LoanApplication::factory()->create([
            'customer_id' => $this->customer->id,
            'loan_product_id' => $this->loanProduct->id,
            'requested_amount' => 5000,
            'requested_tenure' => 12,
            'status' => 'pending',
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
        // First create a loan
        $loan = \App\Models\Loan::factory()->create([
            'user_id' => $this->customer->id,
            'loan_product_id' => $this->loanProduct->id,
            'amount' => 5000,
            'status' => 'approved',
        ]);

        // Create account for the customer
        $account = \App\Models\Account::factory()->create([
            'customer_id' => $this->customer->id,
            'account_type_id' => $this->accountType->id,
        ]);

        // Create account balance
        \App\Models\AccountBalance::factory()->create([
            'account_id' => $account->id,
            'available_balance' => 0,
            'ledger_balance' => 0,
        ]);

        // Login as admin to get token
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('data.access_token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/loans/{$loan->id}/disburse");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Loan disbursed successfully'
                ]);

        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'status' => 'disbursed',
        ]);

        // Check that account balance was updated
        $this->assertDatabaseHas('account_balances', [
            'account_id' => $account->id,
            'available_balance' => 5000,
        ]);
    }

    public function test_customer_can_make_loan_repayment()
    {
        $loan = Loan::factory()->create([
            'user_id' => $this->customer->id,
            'status' => 'disbursed',
            'amount' => 5000,
        ]);

        $account = \App\Models\Account::factory()->create([
            'customer_id' => $this->customer->id,
            'account_type_id' => $this->accountType->id,
        ]);

        // Create account balance with enough funds
        \App\Models\AccountBalance::factory()->create([
            'account_id' => $account->id,
            'available_balance' => 6000,
            'ledger_balance' => 6000,
        ]);

        // Login as customer to get token
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $this->customer->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('data.access_token');

        $repaymentData = [
            'amount' => 500,
            'description' => 'Monthly loan repayment',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/loans/{$loan->id}/repay", $repaymentData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Repayment processed successfully'
                ]);

        // Check that account balance was reduced
        $this->assertDatabaseHas('account_balances', [
            'account_id' => $account->id,
            'available_balance' => 5500, // 6000 - 500
        ]);
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