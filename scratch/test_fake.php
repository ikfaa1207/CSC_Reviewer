<?php

// Boot Laravel application
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

try {
    Http::fake([
        'https://example.com/*' => Http::sequence()
            ->push(['hello' => 'world'], 200)
    ]);

    $response = Http::post('https://example.com/api');
    echo "Success!\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
