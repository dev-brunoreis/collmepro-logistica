<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Cache;

echo "🔓 Limpando locks do schedule...\n\n";

// Limpar locks específicos
$locks = [
    'laravel_schedule:refresh-trackings',
    'laravel_schedule:bling-sync', 
    'laravel_schedule:hourly-stats-ping'
];

foreach ($locks as $lock) {
    if (Cache::has($lock)) {
        Cache::forget($lock);
        echo "✅ Removido: {$lock}\n";
    } else {
        echo "ℹ️  Não encontrado: {$lock}\n";
    }
}

echo "\n🎯 Locks limpos! Agora o schedule pode rodar novamente.\n";
echo "📋 Para testar:\n";
echo "   php artisan schedule:run\n";
echo "   php artisan queue:monitor-status\n";
