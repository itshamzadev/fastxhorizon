<?php

namespace App\Console\Commands;

use App\Models\CustomerSearch;
use App\Models\ProductView;
use Illuminate\Console\Command;

class PruneProductViews extends Command
{
    protected $signature = 'product-views:prune {--days=90 : Delete view/search rows older than this many days}';

    protected $description = 'Remove stale personalization signal rows (product_views, customer_searches)';

    public function handle(): int
    {
        $days = (int)$this->option('days');
        $cutoff = now()->subDays($days);

        $views = ProductView::where('viewed_at', '<', $cutoff)->delete();
        $searches = CustomerSearch::where('searched_at', '<', $cutoff)->delete();

        $this->info("Pruned {$views} product view rows and {$searches} search rows older than {$days} days.");
        return self::SUCCESS;
    }
}
