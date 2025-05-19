<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UidUsage; // Ensure you have the correct model namespace
use App\Models\User;

class ResetWeeklyUsage extends Command
{
    protected $signature = 'reset:weekly_usage';
    protected $description = 'Reset all UidUsage and User weekly_usage to 0';

    public function handle()
    {
        UidUsage::query()->update(['weekly_usage' => 0]);
        User::query()->update(['weekly_usage' => 0]);

        $this->info('Weekly usage has been reset to 0 for all users.');
    }
}
