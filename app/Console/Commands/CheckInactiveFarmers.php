<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckInactiveFarmers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'farmers:check-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks for farmers with no supply (purchases) in the last 60 days and marks them as inactive';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $thresholdDate = \Carbon\Carbon::now()->subDays(60);

        // Find farmers whose latest bill was before the threshold date, or who have no bills
        $inactiveFarmers = \App\Models\Vender::whereDoesntHave('bills', function ($query) use ($thresholdDate) {
            $query->where('bill_date', '>=', $thresholdDate)
                  ->orWhere('created_at', '>=', $thresholdDate);
        })->where('status', '!=', 'Inactive')->get();

        $count = 0;
        foreach ($inactiveFarmers as $farmer) {
            $farmer->status = 'Inactive';
            $farmer->save();
            $count++;

            // We can send a notification here. For now we will just log it.
            \Log::info("Farmer {$farmer->name} ({$farmer->vender_id}) marked as Inactive due to 60 days of inactivity.");
        }

        $this->info("Successfully processed {$count} inactive farmers.");
    }
}
