<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\LoanProduct;
use App\Models\AccountType;

// Find cooperative account types
$coopCodes = ['COOP_SAV_MONTHLY', 'COOP_SAV_6MONTH', 'COOP_SAV_11MONTH', 'COOP_SAV_12MONTH'];
$coopTypes = AccountType::whereIn('code', $coopCodes)->get();
$coopIds = $coopTypes->pluck('id')->map(fn($id) => (string)$id)->all();

// Update Cooperative loan to require any of these coop account types and loan account
$coop = LoanProduct::where('code', 'COOPERATIVE_LOAN')->first();
if ($coop) {
    $coop->required_cooperative_account_type_ids = $coopIds;
    $coop->requires_account = true;
    $coop->allow_topup = false;
    $coop->save();
    echo "Updated COOPERATIVE_LOAN with coop account types\n";
} else {
    echo "COOPERATIVE_LOAN not found\n";
}

// Government loans: set allow_refinance true and require loan account
$govCodes = ['FEDERAL_GOVT_LOAN', 'STATE_GOVT_LOAN', 'LOCAL_GOVT_LOAN'];
foreach ($govCodes as $code) {
    $p = LoanProduct::where('code', $code)->first();
    if ($p) {
        $p->allow_refinance = true;
        $p->allow_topup = true;
        $p->requires_account = true;
        $p->save();
        echo "Updated {$code} allow_refinance and requires_account\n";
    } else {
        echo "{$code} not found\n";
    }
}

// Special loan: require loan account
$special = LoanProduct::where('code', 'SPECIAL_LOAN')->first();
if ($special) {
    $special->requires_account = true;
    $special->allow_topup = false;
    $special->save();
    echo "Updated SPECIAL_LOAN requires_account\n";
} else {
    echo "SPECIAL_LOAN not found\n";
}

// Ensure all loan products require a repayment account
LoanProduct::query()->update(['requires_account' => true]);

echo "Done\n";
