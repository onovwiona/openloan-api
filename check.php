<?php
require 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check loan applications
$appId1 = 'eb805e68-2b81-4585-843d-04707fcffea0';
$appId2 = 'b582ce5a-6ac7-4df4-9b8f-99f65d8dbca2';

echo "=== Checking Loan Applications ===\n";
foreach ([$appId1, $appId2] as $id) {
    $app = App\Models\LoanApplication::find($id);
    if ($app) {
        echo "App $id: customer_id = " . $app->customer_id . ", status = " . $app->status . "\n";
    } else {
        echo "App $id: NOT FOUND\n";
    }
}

echo "\nUser 13 exists: " . (App\Models\User::find(13) ? "Yes" : "No") . "\n";
echo "\n=== All applications for user 13 ===\n";
$apps = App\Models\LoanApplication::where('customer_id', 13)->get();
foreach ($apps as $app) {
    echo "- {$app->id}: status={$app->status}, amount={$app->requested_amount}\n";
}
?>