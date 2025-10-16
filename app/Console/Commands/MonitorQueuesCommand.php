<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorQueuesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:monitor-status 
                            {--refresh=5 : Refresh interval in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor queue status and failed jobs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $refresh = (int) $this->option('refresh');

        $this->info('Queue Monitoring Dashboard');
        $this->info('Press Ctrl+C to exit');
        $this->newLine();

        while (true) {
            // Clear screen
            if (PHP_OS_FAMILY !== 'Windows') {
                system('clear');
            }

            $this->displayHeader();
            $this->displayQueueStatus();
            $this->displayFailedJobs();
            $this->displayRecentFailures();

            sleep($refresh);
        }

        return Command::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('   COLLMEPRO LOGISTICS - QUEUE MONITORING DASHBOARD');
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('Time: ' . now()->format('Y-m-d H:i:s'));
        $this->newLine();
    }

    private function displayQueueStatus(): void
    {
        $this->line('<fg=cyan>┌─ QUEUE STATUS ─────────────────────────────────────┐</>');

        $queues = ['invoices', 'tracking', 'sellers', 'default'];
        
        foreach ($queues as $queueName) {
            $count = DB::table('jobs')
                ->where('queue', $queueName)
                ->count();

            $color = $count > 10 ? 'red' : ($count > 0 ? 'yellow' : 'green');
            $icon = $count > 10 ? '⚠' : ($count > 0 ? '●' : '✓');

            $this->line(sprintf(
                '│ <fg=%s>%s %s:</> %s %s',
                $color,
                $icon,
                str_pad(ucfirst($queueName), 12),
                str_pad($count, 4),
                $count === 1 ? 'job' : 'jobs'
            ));
        }

        $totalJobs = DB::table('jobs')->count();
        $this->line('├────────────────────────────────────────────────────┤');
        $this->line(sprintf('│ <fg=white>Total Pending:</>  %s', str_pad($totalJobs, 28)));
        $this->line('<fg=cyan>└────────────────────────────────────────────────────┘</>');
        $this->newLine();
    }

    private function displayFailedJobs(): void
    {
        $this->line('<fg=red>┌─ FAILED JOBS (Laravel) ────────────────────────────┐</>');

        $failedJobs = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit(5)
            ->get();

        if ($failedJobs->isEmpty()) {
            $this->line('│ <fg=green>✓ No failed jobs</> '.str_repeat(' ', 34).'│');
        } else {
            foreach ($failedJobs as $job) {
                $connection = json_decode($job->payload, true)['displayName'] ?? 'Unknown';
                $this->line(sprintf(
                    '│ <fg=red>✗</> %s',
                    str_pad(substr($connection, 0, 40), 48).' │'
                ));
            }
        }

        $this->line('<fg=red>└────────────────────────────────────────────────────┘</>');
        $this->newLine();
    }

    private function displayRecentFailures(): void
    {
        $this->line('<fg=magenta>┌─ SYNC FAILURES (Last 24h) ─────────────────────────┐</>');

        $failures = DB::table('failed_sync_jobs')
            ->where('resolved', false)
            ->where('failed_at', '>=', now()->subDay())
            ->select('job_type', DB::raw('COUNT(*) as count'))
            ->groupBy('job_type')
            ->get();

        if ($failures->isEmpty()) {
            $this->line('│ <fg=green>✓ No sync failures in last 24 hours</> '.str_repeat(' ', 15).'│');
        } else {
            foreach ($failures as $failure) {
                $this->line(sprintf(
                    '│ <fg=magenta>●</> %s: %s %s',
                    str_pad($failure->job_type, 25),
                    str_pad($failure->count, 3),
                    str_pad($failure->count === 1 ? 'failure' : 'failures', 14).' │'
                ));
            }
        }

        $totalUnresolved = DB::table('failed_sync_jobs')
            ->where('resolved', false)
            ->count();

        if ($totalUnresolved > 0) {
            $this->line('├────────────────────────────────────────────────────┤');
            $this->line(sprintf(
                '│ <fg=yellow>Total Unresolved:</>  %s',
                str_pad($totalUnresolved, 29).' │'
            ));
        }

        $this->line('<fg=magenta>└────────────────────────────────────────────────────┘</>');
        $this->newLine();

        $this->line('<fg=gray>Refreshing every ' . $this->option('refresh') . ' seconds... Press Ctrl+C to exit</>');
    }
}
