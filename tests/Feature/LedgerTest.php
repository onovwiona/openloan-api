<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use Spatie\Permission\Models\Role;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $auditor;
    protected $accountType;
    protected $ledgerAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'auditor']);

        // Create users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->auditor = User::factory()->create();
        $this->auditor->assignRole('auditor');

        // Create account type and ledger account
        $this->accountType = AccountType::factory()->create();
        $this->ledgerAccount = LedgerAccount::factory()->create([
            'code' => '1000',
            'name' => 'Cash',
            'type' => 'asset',
        ]);
    }

    /** @test */
    public function admin_can_get_trial_balance()
    {
        // Create some journal entries to test trial balance
        $journalEntry = JournalEntry::factory()->create([
            'description' => 'Test transaction',
            'date' => now(),
        ]);

        JournalLine::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'ledger_account_id' => $this->ledgerAccount->id,
            'debit' => 1000,
            'credit' => 0,
        ]);

        JournalLine::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'ledger_account_id' => $this->ledgerAccount->id,
            'debit' => 0,
            'credit' => 500,
        ]);

        $token = auth()->login($this->admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/ledger/trial-balance');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'total_debit',
                        'total_credit',
                        'is_balanced',
                        'accounts'
                    ]
                ]);

        $data = $response->json('data');
        $this->assertEquals(1000, $data['total_debit']);
        $this->assertEquals(500, $data['total_credit']);
        $this->assertFalse($data['is_balanced']); // 1000 != 500
    }

    /** @test */
    public function auditor_can_get_trial_balance()
    {
        $token = auth()->login($this->auditor);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/ledger/trial-balance');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ]);
    }

    /** @test */
    public function admin_can_close_daily_books()
    {
        $token = auth()->login($this->admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/ledger/close-day');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Daily books closed successfully'
                ]);

        // Check that a daily closing record was created
        $this->assertDatabaseHas('daily_closings', [
            'closed_by' => $this->admin->id,
        ]);
    }

    /** @test */
    public function auditor_cannot_close_daily_books()
    {
        $token = auth()->login($this->auditor);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/ledger/close-day');

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthorized'
                ]);
    }

    /** @test */
    public function balanced_trial_balance_shows_correct_totals()
    {
        // Create balanced journal entries
        $journalEntry = JournalEntry::factory()->create([
            'description' => 'Balanced transaction',
            'date' => now(),
        ]);

        JournalLine::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'ledger_account_id' => $this->ledgerAccount->id,
            'debit' => 1000,
            'credit' => 0,
        ]);

        $liabilityAccount = LedgerAccount::factory()->create([
            'code' => '2000',
            'name' => 'Loans Payable',
            'type' => 'liability',
        ]);

        JournalLine::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'ledger_account_id' => $liabilityAccount->id,
            'debit' => 0,
            'credit' => 1000,
        ]);

        $token = auth()->login($this->admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/ledger/trial-balance');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(1000, $data['total_debit']);
        $this->assertEquals(1000, $data['total_credit']);
        $this->assertTrue($data['is_balanced']);
    }

    /** @test */
    public function customer_cannot_access_trial_balance()
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $token = auth()->login($customer);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/ledger/trial-balance');

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Unauthorized'
                ]);
    }
}