<?php

namespace Database\Seeders;

use App\Models\CustomerAttribution;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerAttributionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the marketer and office staff
        $marketer = User::whereHas('roles', function ($query) {
            $query->where('name', 'marketer');
        })->first();

        $office = User::whereHas('roles', function ($query) {
            $query->where('name', 'office');
        })->first();

        // Get customers ordered by creation (oldest first)
        $customers = User::whereHas('roles', function ($query) {
            $query->where('name', 'customer');
        })->orderBy('id')->get();

        // Customer 1, 2, 3 - attributed to marketer
        if ($marketer && $customers->count() >= 3) {
            CustomerAttribution::factory()->create([
                'customer_user_id' => $customers[0]->id,
                'source_type' => 'marketer',
                'source_user_id' => $marketer->id,
                'status' => 'verified',
            ]);

            CustomerAttribution::factory()->create([
                'customer_user_id' => $customers[1]->id,
                'source_type' => 'marketer',
                'source_user_id' => $marketer->id,
                'status' => 'verified',
            ]);

            CustomerAttribution::factory()->create([
                'customer_user_id' => $customers[2]->id,
                'source_type' => 'marketer',
                'source_user_id' => $marketer->id,
                'status' => 'verified',
            ]);
        }

        // Customer 4 - attributed to office
        if ($office && $customers->count() >= 4) {
            CustomerAttribution::factory()->create([
                'customer_user_id' => $customers[3]->id,
                'source_type' => 'staff',
                'source_user_id' => $office->id,
                'status' => 'verified',
            ]);
        }

        // Customer 5 - organic (no attribution)
        if ($customers->count() >= 5) {
            CustomerAttribution::factory()->create([
                'customer_user_id' => $customers[4]->id,
                'source_type' => 'organic',
                'source_user_id' => null,
                'status' => 'verified',
            ]);
        }
    }
}
