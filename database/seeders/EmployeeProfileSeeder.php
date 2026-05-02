<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get users with employee roles
        $employees = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['marketer', 'office', 'admin']);
        })->get();

        foreach ($employees as $employee) {
            EmployeeProfile::factory()->create([
                'user_id' => $employee->id,
            ]);
        }
    }
}
