<?php

namespace App\Console\Commands;

use App\Jobs\ImportWooCommerceOrdersJob;
use Illuminate\Console\Command;

class ImportOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:import {system? : Specific system (collmepro, collmepro_wholesale)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import orders from WooCommerce systems';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $system = $this->argument('system');

        if ($system) {
            // Import specific system
            if (!in_array($system, ['collmepro', 'collmepro_wholesale'])) {
                $this->error("Invalid system. Use: collmepro or collmepro_wholesale");
                return 1;
            }

            $this->info("Starting import of system: {$system}");
            ImportWooCommerceOrdersJob::dispatch($system);
            $this->info("Job sent to queue!");
        } else {
            // Import all systems
            $this->info("Starting import of all systems...");
            
            $this->info("1. Importing Collmepro...");
            ImportWooCommerceOrdersJob::dispatch('collmepro');
            
            $this->info("2. Importing Collmepro Wholesale...");
            ImportWooCommerceOrdersJob::dispatch('collmepro_wholesale');
            
            $this->info("Jobs sent to queue!");
        }

        return 0;
    }
}
