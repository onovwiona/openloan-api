<?php

namespace Database\Seeders;

use App\Models\LoanDocumentType;
use App\Models\LoanProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GovernmentLoanProductsSeeder extends Seeder
{
    public function run(): void
    {
        // Create document types
        $documentTypes = [
            ['code' => 'APPOINTMENT_LETTER', 'name' => 'Appointment Letter', 'description' => 'Government employee appointment letter'],
            ['code' => 'PAYSLIP', 'name' => 'Payslip', 'description' => 'Recent payslip showing gross and net amounts'],
            ['code' => 'GOVERNMENT_ID_CARD', 'name' => 'Government ID Card', 'description' => 'Valid government issued ID card'],
            ['code' => 'COLLATERAL_DOCUMENTS', 'name' => 'Collateral Documents', 'description' => 'Documents for collateral/security'],
            ['code' => 'DEEDS_DOCUMENT', 'name' => 'Deeds Document', 'description' => 'Property deed or ownership document'],
            ['code' => 'BANK_STATEMENT', 'name' => 'Bank Statement', 'description' => 'Recent bank account statement'],
            ['code' => 'PROOF_OF_FUNDS', 'name' => 'Proof of Funds', 'description' => 'Evidence of available funds'],
        ];

        $createdTypes = [];
        foreach ($documentTypes as $type) {
            $existing = LoanDocumentType::where('code', $type['code'])->first();
            if (!$existing) {
                $created = LoanDocumentType::create([
                    'id' => Str::uuid(),
                    'code' => $type['code'],
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'required' => true,
                    'active' => true,
                ]);
                $createdTypes[$type['code']] = (string)$created->id;
            } else {
                $createdTypes[$type['code']] = (string)$existing->id;
            }
        }

        // 1. Federal Government Loan
        $federalGovt = LoanProduct::updateOrCreate(
            ['code' => 'FEDERAL_GOVT_LOAN'],
            [
                'id' => Str::uuid(),
                'name' => 'Federal Government Loan',
                'description' => 'For government certified workers (local, state, or federal government staffs with valid and verifiable employer ID via government computer IDs)',
                'min_amount' => 50000,
                'max_amount' => 500000,
                'interest_type' => 'reducing',
                'interest_rate' => 4.00,
                'tenure_min_months' => 1,
                'tenure_max_months' => 12,
                'processing_fee' => 0,
                'service_charge' => 2.00,
                'form_fee' => 1075.00,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0,
                'requires_account' => false,
                'requires_passport' => true,
                'requires_guarantor' => false,
                'requires_collateral' => false,
                'requires_bank_statement' => false,
                'requires_proof_income' => false,
                'requires_employment_profile' => true,
                'required_employer_type' => 'government',
                'repayment_schedules' => json_encode(['monthly']),
                'threshold_amount' => 100000,
                'active' => true,
            ]
        );

        // Attach document types to Federal Govt Loan
        $federalGovt->requiredDocumentTypes()->syncWithoutDetaching([
            $createdTypes['APPOINTMENT_LETTER'],
            $createdTypes['PAYSLIP'],
            $createdTypes['GOVERNMENT_ID_CARD'],
        ]);

        // 2. State Government Loan
        $stateGovt = LoanProduct::updateOrCreate(
            ['code' => 'STATE_GOVT_LOAN'],
            [
                'id' => Str::uuid(),
                'name' => 'State Government Loan',
                'description' => 'For state government employees with valid employment documentation',
                'min_amount' => 50000,
                'max_amount' => 500000,
                'interest_type' => 'reducing',
                'interest_rate' => 4.00,
                'tenure_min_months' => 1,
                'tenure_max_months' => 36,
                'processing_fee' => 0,
                'service_charge' => 2.00,
                'form_fee' => 1075.00,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0,
                'requires_account' => false,
                'requires_passport' => true,
                'requires_guarantor' => false,
                'requires_collateral' => false,
                'requires_bank_statement' => false,
                'requires_proof_income' => false,
                'requires_employment_profile' => true,
                'required_employer_type' => 'government',
                'repayment_schedules' => json_encode(['monthly']),
                'threshold_amount' => 100000,
                'active' => true,
            ]
        );

        // Attach document types to State Govt Loan
        $stateGovt->requiredDocumentTypes()->syncWithoutDetaching([
            $createdTypes['APPOINTMENT_LETTER'],
            $createdTypes['PAYSLIP'],
            $createdTypes['GOVERNMENT_ID_CARD'],
        ]);

        // 3. Local Government Loan
        $localGovt = LoanProduct::updateOrCreate(
            ['code' => 'LOCAL_GOVT_LOAN'],
            [
                'id' => Str::uuid(),
                'name' => 'Local Government Loan',
                'description' => 'For local government employees with valid employment documentation',
                'min_amount' => 50000,
                'max_amount' => 500000,
                'interest_type' => 'reducing',
                'interest_rate' => 4.00,
                'tenure_min_months' => 1,
                'tenure_max_months' => 36,
                'processing_fee' => 0,
                'service_charge' => 2.00,
                'form_fee' => 1075.00,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0,
                'requires_account' => false,
                'requires_passport' => true,
                'requires_guarantor' => false,
                'requires_collateral' => false,
                'requires_bank_statement' => false,
                'requires_proof_income' => false,
                'requires_employment_profile' => true,
                'required_employer_type' => 'government',
                'repayment_schedules' => json_encode(['monthly']),
                'threshold_amount' => 100000,
                'active' => true,
            ]
        );

        // Attach document types to Local Govt Loan
        $localGovt->requiredDocumentTypes()->syncWithoutDetaching([
            $createdTypes['APPOINTMENT_LETTER'],
            $createdTypes['PAYSLIP'],
            $createdTypes['GOVERNMENT_ID_CARD'],
        ]);

        // 4. Cooperative Loan
        $cooperative = LoanProduct::updateOrCreate(
            ['code' => 'COOPERATIVE_LOAN'],
            [
                'id' => Str::uuid(),
                'name' => 'Cooperative Loan',
                'description' => 'For cooperative members with sufficient account balance. Loan amount determined by savings amount.',
                'min_amount' => 50000,
                'max_amount' => 1000000,
                'interest_type' => 'reducing',
                'interest_rate' => 7.00,
                'tenure_min_months' => 1,
                'tenure_max_months' => 12,
                'processing_fee' => 0,
                'service_charge' => 3.15,
                'form_fee' => 1075.00,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0,
                'requires_account' => true,
                'requires_passport' => true,
                'requires_guarantor' => false,
                'requires_collateral' => false,
                'requires_bank_statement' => false,
                'requires_proof_income' => false,
                'repayment_schedules' => json_encode(['daily', 'weekly', 'bi_weekly', 'monthly']),
                'threshold_amount' => 250000,
                'active' => true,
            ]
        );

        // Attach document types to Cooperative Loan
        $cooperative->requiredDocumentTypes()->syncWithoutDetaching([
            $createdTypes['COLLATERAL_DOCUMENTS'],
        ]);

        // 5. Special Loan
        $special = LoanProduct::updateOrCreate(
            ['code' => 'SPECIAL_LOAN'],
            [
                'id' => Str::uuid(),
                'name' => 'Special Loan',
                'description' => 'General purpose loan requiring collateral and multiple guarantors with extensive documentation',
                'min_amount' => 100000,
                'max_amount' => 2000000,
                'interest_type' => 'reducing',
                'interest_rate' => 6.00,
                'tenure_min_months' => 1,
                'tenure_max_months' => 6,
                'processing_fee' => 0,
                'service_charge' => 3.15,
                'form_fee' => 1075.00,
                'allow_early_repayment' => true,
                'early_repayment_penalty' => 0,
                'requires_account' => true,
                'requires_passport' => true,
                'requires_guarantor' => true,
                'min_guarantors' => 2,
                'requires_collateral' => true,
                'requires_bank_statement' => true,
                'requires_proof_income' => true,
                'repayment_schedules' => json_encode(['daily', 'weekly', 'bi_weekly', 'monthly']),
                'threshold_amount' => 500000,
                'active' => true,
            ]
        );

        // Attach document types to Special Loan
        $special->requiredDocumentTypes()->syncWithoutDetaching([
            $createdTypes['COLLATERAL_DOCUMENTS'],
            $createdTypes['DEEDS_DOCUMENT'],
            $createdTypes['BANK_STATEMENT'],
            $createdTypes['PROOF_OF_FUNDS'],
        ]);
    }
}
