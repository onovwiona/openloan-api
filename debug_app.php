<?php
require 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$appId = 'eb805e68-2b81-4585-843d-04707fcffea0';
$application = App\Models\LoanApplication::find($appId);

if ($application) {
    echo "Application found:\n";
    echo "- ID: {$application->id}\n";
    echo "- Customer ID: {$application->customer_id}\n";
    echo "- Status: {$application->status}\n";
    echo "- Product: " . ($application->loanProduct ? $application->loanProduct->name : 'N/A') . "\n";
} else {
    echo "Application not found\n";
}

echo "\nAll applications:\n";
$apps = App\Models\LoanApplication::all();
foreach ($apps as $app) {
    echo "- {$app->id}: customer={$app->customer_id}, status={$app->status}\n";
}
?>