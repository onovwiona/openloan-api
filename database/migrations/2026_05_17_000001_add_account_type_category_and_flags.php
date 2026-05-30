<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_types', function (Blueprint $table) {
            $table->enum('account_category', ['ASSET', 'LIABILITY', 'CUSTOMER_DEPOSIT', 'LOAN', 'ESCROW', 'INTERNAL_GL'])
                ->default('CUSTOMER_DEPOSIT')
                ->after('code');
            $table->enum('normal_balance', ['DEBIT', 'CREDIT'])
                ->default('DEBIT')
                ->after('account_category');
            $table->boolean('is_customer_visible')->default(true)->after('description');
            $table->boolean('supports_deposit')->default(true)->after('is_customer_visible');
            $table->boolean('supports_withdrawal')->default(true)->after('supports_deposit');
            $table->boolean('supports_transfer')->default(true)->after('supports_withdrawal');
            $table->boolean('requires_kyc')->default(false)->after('supports_transfer');
        });

        DB::table('account_types')->where('code', 'WAL')->update([
            'account_category' => 'CUSTOMER_DEPOSIT',
            'normal_balance' => 'DEBIT',
            'is_customer_visible' => true,
            'supports_deposit' => true,
            'supports_withdrawal' => true,
            'supports_transfer' => true,
            'requires_kyc' => true,
        ]);

        DB::table('account_types')->where('code', 'SAV')->update([
            'account_category' => 'CUSTOMER_DEPOSIT',
            'normal_balance' => 'DEBIT',
            'is_customer_visible' => true,
            'supports_deposit' => true,
            'supports_withdrawal' => true,
            'supports_transfer' => true,
            'requires_kyc' => true,
        ]);

        DB::table('account_types')->where('code', 'COOP')->update([
            'account_category' => 'CUSTOMER_DEPOSIT',
            'normal_balance' => 'DEBIT',
            'is_customer_visible' => true,
            'supports_deposit' => true,
            'supports_withdrawal' => true,
            'supports_transfer' => true,
            'requires_kyc' => true,
        ]);

        DB::table('account_types')->where('code', 'JOINT')->update([
            'account_category' => 'CUSTOMER_DEPOSIT',
            'normal_balance' => 'DEBIT',
            'is_customer_visible' => true,
            'supports_deposit' => true,
            'supports_withdrawal' => true,
            'supports_transfer' => true,
            'requires_kyc' => true,
        ]);

        DB::table('account_types')->where('code', 'SAL')->update([
            'account_category' => 'CUSTOMER_DEPOSIT',
            'normal_balance' => 'DEBIT',
            'is_customer_visible' => true,
            'supports_deposit' => true,
            'supports_withdrawal' => true,
            'supports_transfer' => true,
            'requires_kyc' => true,
        ]);

        DB::table('account_types')->where('code', 'ESC')->update([
            'account_category' => 'ESCROW',
            'normal_balance' => 'DEBIT',
            'is_customer_visible' => true,
            'supports_deposit' => true,
            'supports_withdrawal' => false,
            'supports_transfer' => false,
            'requires_kyc' => true,
        ]);

        DB::table('account_types')->where('code', 'LOAN_REPAY')->update([
            'code' => 'LOAN',
            'name' => 'Loan Account',
            'account_category' => 'LOAN',
            'normal_balance' => 'CREDIT',
            'is_customer_visible' => true,
            'supports_deposit' => false,
            'supports_withdrawal' => false,
            'supports_transfer' => false,
            'requires_kyc' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('account_types', function (Blueprint $table) {
            $table->dropColumn([
                'account_category',
                'normal_balance',
                'is_customer_visible',
                'supports_deposit',
                'supports_withdrawal',
                'supports_transfer',
                'requires_kyc',
            ]);
        });

        DB::table('account_types')->where('code', 'LOAN')->update([
            'code' => 'LOAN_REPAY',
            'name' => 'Loan Repayment Account',
        ]);
    }
};
