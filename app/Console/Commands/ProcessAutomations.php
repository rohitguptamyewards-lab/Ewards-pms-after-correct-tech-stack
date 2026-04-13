<?php
namespace App\Console\Commands;

use App\Services\AutomationService;
use Illuminate\Console\Command;

class ProcessAutomations extends Command
{
    protected $signature = 'automations:process';
    protected $description = 'Process all active scheduled automations';

    public function handle(AutomationService $service): int
    {
        $this->info('Processing scheduled automations...');
        $service->processScheduledAutomations();
        $this->info('Done.');
        return 0;
    }
}
