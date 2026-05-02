<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder

{

    use WithoutModelEvents;



    public function run(): void

    {

        $this->call([

            RoleSeeder::class,

            UserSeeder::class,

            AccountTypeSeeder::class,

            LoanProductSeeder::class,

            LedgerAccountSeeder::class,

            EmployeeProfileSeeder::class,

            CustomerProfileSeeder::class,

            CustomerAttributionSeeder::class,

            CommissionRuleSeeder::class,

            \Database\Seeders\ProjectExperienceSeeder::class,

        ]);

    }

}
