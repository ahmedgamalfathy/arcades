<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

config(['database.default' => 'tenant']);

echo "=== SessionDevice Table Structure ===\n\n";

$columns = DB::connection('tenant')->select('DESCRIBE session_devices');

foreach ($columns as $column) {
    echo "{$column->Field} ({$column->Type}) - Null: {$column->Null}, Default: {$column->Default}\n";
}
