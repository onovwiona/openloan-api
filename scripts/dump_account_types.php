<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AccountType;

$types = AccountType::orderBy('code')->get(['id','code','name'])->toArray();
echo json_encode($types, JSON_PRETTY_PRINT);
