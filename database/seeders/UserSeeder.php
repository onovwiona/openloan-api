<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Marketer
        $marketer = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Marketer',
            'email' => 'john.marketer@opendoor.com',
            'phone' => '+2348000000001',
        ]);
        $marketer->syncRoles(['marketer']);

        // Create Office Staff
        $office = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Office',
            'email' => 'jane.office@opendoor.com',
            'phone' => '+2348000000002',
        ]);
        $office->syncRoles(['office']);

        // Create 5 Customers
        // Customer 1 - attributed to marketer
        $customer1 = User::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Customer1',
            'email' => 'alice.customer1@example.com',
            'phone' => '+2348000000011',
        ]);
        $customer1->roles()->attach(4); // customer role

        // Customer 2 - attributed to marketer
        $customer2 = User::factory()->create([
            'first_name' => 'Bob',
            'last_name' => 'Customer2',
            'email' => 'bob.customer2@example.com',
            'phone' => '+2348000000012',
        ]);
        $customer2->roles()->attach(4); // customer role

        // Customer 3 - attributed to marketer
        $customer3 = User::factory()->create([
            'first_name' => 'Charlie',
            'last_name' => 'Customer3',
            'email' => 'charlie.customer3@example.com',
            'phone' => '+2348000000013',
        ]);
        $customer3->roles()->attach(4); // customer role

        // Customer 4 - attributed to office
        $customer4 = User::factory()->create([
            'first_name' => 'Diana',
            'last_name' => 'Customer4',
            'email' => 'diana.customer4@example.com',
            'phone' => '+2348000000014',
        ]);
        $customer4->roles()->attach(4); // customer role

        // Customer 5 - organic (no attribution)
        $customer5 = User::factory()->create([
            'first_name' => 'Eve',
            'last_name' => 'Organic',
            'email' => 'eve.organic@example.com',
            'phone' => '+2348000000015',
        ]);
        $customer5->roles()->attach(4); // customer role
    }
}
