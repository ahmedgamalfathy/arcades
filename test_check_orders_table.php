<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== Checking orders table structure ===\n\n";

$columns = DB::select('DESCRIBE orders');

echo "Columns in orders table:\n";
foreach ($columns as $col) {
    echo "  - {$col->Field} ({$col->Type})\n";
}
