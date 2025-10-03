<?php

namespace App\Console\Commands;

use App\Jobs\ImportShopifyOrdersJob;
use App\Jobs\ImportWooCommerceOrdersJob;
use Illuminate\Console\Command;

class ImportOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:import {system? : Specific system (collmepro, collmepro_atacadista, collme_beauty)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import orders from WooCommerce and Shopify systems';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $system = $this->argument('system');

        if ($system) {
            // Import specific system
            if (!in_array($system, ['collmepro', 'collmepro_atacadista', 'collme_beauty'])) {
                $this->error("Invalid system. Use: collmepro, collmepro_atacadista, or collme_beauty");
                return 1;
            }

            $this->info("Starting import of system: {$system}");
            
            if (in_array($system, ['collmepro', 'collmepro_atacadista'])) {
                ImportWooCommerceOrdersJob::dispatch($system);
            } elseif ($system === 'collme_beauty') {
                ImportShopifyOrdersJob::dispatch('shopify');
            }
            
            $this->info("Job sent to queue!");
        } else {
            // Import all systems
            $this->info("Starting import of all systems...");
            
            $this->info("1. Importing Collmepro...");
            ImportWooCommerceOrdersJob::dispatch('collmepro');
            
            $this->info("2. Importing Collmepro Atacadista...");
            ImportWooCommerceOrdersJob::dispatch('collmepro_atacadista');
            
            $this->info("3. Importing Collme Beauty...");
            ImportShopifyOrdersJob::dispatch('shopify');
            
            $this->info("Jobs sent to queue!");
        }

        return 0;
    }
}
