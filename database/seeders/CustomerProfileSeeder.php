<?php

namespace Database\Seeders;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get users with customer role
        $customers = User::whereHas('roles', function ($query) {
            $query->where('name', 'customer');
        })->get();

        foreach ($customers as $customer) {
            CustomerProfile::factory()->create([
                'user_id' => $customer->id,
            ]);
        }
    }
}
