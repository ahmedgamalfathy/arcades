<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== List of All Dailies ===\n\n";

$dailies = App\Models\Daily\Daily::orderBy('id', 'desc')->get();

echo "Total Dailies: {$dailies->count()}\n\n";

foreach ($dailies as $daily) {
    echo "Daily ID: {$daily->id}\n";
    echo "  Start: {$daily->start_date_time}\n";
    echo "  End: " . ($daily->end_date_time ?? 'Still Open') . "\n";
    echo "  Income: " . ($daily->total_income ?? 'N/A') . "\n";
    echo "  Expense: " . ($daily->total_expense ?? 'N/A') . "\n";
    echo "  Profit: " . ($daily->total_profit ?? 'N/A') . "\n";
    echo "\n";
}
