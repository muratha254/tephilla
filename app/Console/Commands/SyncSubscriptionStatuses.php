<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Support\SubscriptionCatalog;
use Illuminate\Console\Command;

class SyncSubscriptionStatuses extends Command
{
    protected $signature = 'subscription:sync-status';

    protected $description = 'Mark expired subscriptions from their expiry dates. Access control still checks dates on every request.';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = 0;
        Subscription::query()->with('plan')->chunkById(100, function ($rows) use ($subscriptions, &$count) {
            foreach ($rows as $subscription) {
                $before = $subscription->status;
                $updated = $subscriptions->syncDerivedStatus($subscription);
                if ($updated->status !== $before) {
                    $count++;
                }
            }
        });

        $this->info('Updated ' . $count . ' subscription record(s).');
        $this->comment('Statuses: ' . implode(', ', array_keys(SubscriptionCatalog::statuses())));

        return 0;
    }
}
