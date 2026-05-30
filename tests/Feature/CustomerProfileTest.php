<?php

namespace Tests\Feature;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'customer']);
    }

    public function test_customer_can_update_profile_before_kyc_verification()
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+2348000000001',
            'email_verified_at' => null,
            'phone_verified_at' => null,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('customer');

        CustomerProfile::create([
            'user_id' => $user->id,
            'address' => 'Old address',
            'employment_status' => 'unemployed',
            'monthly_income' => 10000,
            'kyc_status' => 'pending',
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.access_token');

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone' => '+1234567890',
            'address' => 'ogbogonogo street main 54',
            'dob' => '1990-01-01',
            'nin' => '1234567890',
            'bvn' => '12345678901',
            'employment_status' => 'employed',
            'monthly_income' => '50000',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/v1/user/customer-profile', $payload);

        $response->assertStatus(200)
            ->assertJson([ 'success' => true, 'message' => 'Customer profile updated successfully' ])
            ->assertJsonPath('data.user.first_name', 'John')
            ->assertJsonPath('data.user.last_name', 'Smith')
            ->assertJsonPath('data.user.phone', '+1234567890')
            ->assertJsonPath('data.address', 'ogbogonogo street main 54')
            ->assertJsonPath('data.nin', '1234567890')
            ->assertJsonPath('data.employment_status', 'employed');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone' => '+1234567890',
        ]);
    }

    public function test_customer_cannot_update_profile_after_kyc_verification()
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+2348000000001',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('customer');

        CustomerProfile::create([
            'user_id' => $user->id,
            'address' => 'Old address',
            'employment_status' => 'employed',
            'monthly_income' => 50000,
            'kyc_status' => 'verified',
            'kyc_verified_at' => now(),
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.access_token');

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone' => '+1234567890',
            'address' => 'ogbogonogo street main 54',
            'monthly_income' => '75000',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson('/api/v1/user/customer-profile', $payload);

        $response->assertStatus(422)
            ->assertJson([ 'success' => false ])
            ->assertJsonPath('errors.kyc_status.0', 'Profile data is locked after verification.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+2348000000001',
        ]);

        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $user->id,
            'address' => 'Old address',
            'monthly_income' => 50000,
            'kyc_status' => 'verified',
        ]);
    }

    public function test_admin_can_update_verified_profile_and_reset_kyc()
    {
        Role::create(['name' => 'admin']);

        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+2348000000001',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('customer');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $profile = CustomerProfile::create([
            'user_id' => $user->id,
            'address' => 'Old address',
            'employment_status' => 'employed',
            'monthly_income' => 50000,
            'kyc_status' => 'verified',
            'kyc_verified_at' => now(),
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.access_token');

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone' => '+1234567890',
            'address' => 'ogbogonogo street main 54',
            'monthly_income' => 75000,
            'update_reason' => 'Updated customer address after document review',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson("/api/v1/customer-profiles/{$profile->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([ 'message' => 'Customer profile updated successfully' ])
            ->assertJsonPath('data.user.first_name', 'John')
            ->assertJsonPath('data.user.phone', '+1234567890')
            ->assertJsonPath('data.address', 'ogbogonogo street main 54')
            ->assertJsonPath('data.kyc_status', 'pending');

        $this->assertDatabaseHas('customer_profiles', [
            'id' => $profile->id,
            'address' => 'ogbogonogo street main 54',
            'monthly_income' => 75000,
            'kyc_status' => 'pending',
            'kyc_verified_at' => null,
            'profile_update_note' => 'Updated customer address after document review',
            'profile_updated_by' => $admin->id,
        ]);
    }

    public function test_admin_can_update_verified_contact_and_reset_verification()
    {
        Role::create(['name' => 'admin']);

        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '+2348000000001',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('customer');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $profile = CustomerProfile::create([
            'user_id' => $user->id,
            'address' => 'Old address',
            'employment_status' => 'employed',
            'monthly_income' => 50000,
            'kyc_status' => 'verified',
            'kyc_verified_at' => now(),
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.access_token');

        $payload = [
            'email' => 'jane.new@example.com',
            'phone' => '+2348000000002',
            'update_reason' => 'Admin updated verified contact details',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->patchJson("/api/v1/customer-profiles/{$profile->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([ 'message' => 'Customer profile updated successfully' ])
            ->assertJsonPath('data.user.email', 'jane.new@example.com')
            ->assertJsonPath('data.user.phone', '+2348000000002')
            ->assertJsonPath('data.kyc_status', 'pending');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'jane.new@example.com',
            'phone' => '+2348000000002',
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }
}
