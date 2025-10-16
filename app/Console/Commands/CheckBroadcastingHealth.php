<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CheckBroadcastingHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'broadcasting:health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check broadcasting configuration and server health';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Checking Broadcasting Configuration...');
        $this->newLine();

        $allGood = true;

        // 1. Check broadcasting driver
        $driver = config('broadcasting.default');
        $this->checkItem(
            'Broadcasting Driver',
            $driver,
            in_array($driver, ['reverb', 'pusher', 'log', 'null'])
        );

        if ($driver === 'null') {
            $this->warn('⚠️  Broadcasting is disabled (driver: null)');
            $this->info('   This is OK for development, but real-time updates will not work.');
            $this->newLine();
        }

        // 2. Check Reverb configuration (if using Reverb)
        if ($driver === 'reverb') {
            $reverbHost = config('broadcasting.connections.reverb.options.host');
            $reverbPort = config('broadcasting.connections.reverb.options.port');
            $reverbKey = config('broadcasting.connections.reverb.key');
            $reverbSecret = config('broadcasting.connections.reverb.secret');
            $reverbAppId = config('broadcasting.connections.reverb.app_id');

            $this->checkItem('Reverb Host', $reverbHost, !empty($reverbHost));
            $this->checkItem('Reverb Port', $reverbPort, !empty($reverbPort));
            $this->checkItem('Reverb App Key', $reverbKey ? '***' : 'NOT SET', !empty($reverbKey));
            $this->checkItem('Reverb App Secret', $reverbSecret ? '***' : 'NOT SET', !empty($reverbSecret));
            $this->checkItem('Reverb App ID', $reverbAppId, !empty($reverbAppId));

            $this->newLine();

            // 3. Check if Reverb server is running
            if (!empty($reverbHost) && !empty($reverbPort)) {
                $this->info('🔌 Checking Reverb Server Connection...');
                
                try {
                    $url = "http://{$reverbHost}:{$reverbPort}/app/{$reverbKey}";
                    $response = Http::timeout(3)->get($url);
                    
                    if ($response->successful()) {
                        $this->checkItem('Reverb Server', "Running on {$reverbHost}:{$reverbPort}", true);
                    } else {
                        $this->checkItem('Reverb Server', "Not responding (HTTP {$response->status()})", false);
                        $allGood = false;
                        $this->warn('   💡 Start Reverb: php artisan reverb:start');
                    }
                } catch (\Exception $e) {
                    $this->checkItem('Reverb Server', 'Not reachable', false);
                    $allGood = false;
                    $this->warn('   💡 Start Reverb: php artisan reverb:start');
                    $this->warn('   💡 Or use: composer run dev');
                }
            }
        }

        $this->newLine();

        // 4. Check circuit breaker status
        $failures = Cache::get('broadcasting:circuit_breaker', 0);
        $circuitOpen = $failures >= 5;
        
        $this->checkItem(
            'Circuit Breaker',
            $circuitOpen ? "OPEN ({$failures} failures)" : "CLOSED ({$failures} failures)",
            !$circuitOpen
        );

        if ($circuitOpen) {
            $this->warn('   💡 Reset circuit breaker: php artisan cache:forget broadcasting:circuit_breaker');
        }

        $this->newLine();

        // 5. Check frontend configuration
        $this->info('🎨 Frontend Configuration:');
        $this->table(
            ['Variable', 'Value'],
            [
                ['VITE_PUSHER_APP_KEY', env('VITE_PUSHER_APP_KEY', 'NOT SET')],
                ['VITE_PUSHER_HOST', env('VITE_PUSHER_HOST', 'NOT SET')],
                ['VITE_PUSHER_PORT', env('VITE_PUSHER_PORT', 'NOT SET')],
                ['VITE_PUSHER_SCHEME', env('VITE_PUSHER_SCHEME', 'NOT SET')],
            ]
        );

        $this->newLine();

        // 6. Provide recommendations
        if (!$allGood) {
            $this->error('❌ Broadcasting Configuration Issues Detected');
            $this->newLine();
            $this->info('📋 Recommendations:');
            $this->info('   1. Ensure .env has correct REVERB_* variables');
            $this->info('   2. Start Reverb server: php artisan reverb:start');
            $this->info('   3. Or use dev command: composer run dev');
            $this->info('   4. Check firewall settings (port ' . ($reverbPort ?? '8080') . ')');
            $this->newLine();
            return Command::FAILURE;
        }

        if ($driver === 'null') {
            $this->comment('⚠️  Broadcasting is disabled but configuration is valid.');
            return Command::SUCCESS;
        }

        $this->info('✅ Broadcasting is properly configured and healthy!');
        $this->newLine();
        
        return Command::SUCCESS;
    }

    /**
     * Display a check item with status
     */
    private function checkItem(string $label, string $value, bool $ok): void
    {
        $icon = $ok ? '✅' : '❌';
        $status = $ok ? '<info>OK</info>' : '<error>FAIL</error>';
        $this->line(sprintf('  %s %-25s: %-30s [%s]', $icon, $label, $value, $status));
    }
}

