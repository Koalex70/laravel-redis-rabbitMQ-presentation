<?php

namespace App\Console\Commands;

use App\Services\Queue\ReportJobQueueService;
use Illuminate\Console\Command;

class ReportQueueWorkerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:queue-worker {--once : Process only one item and exit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process report jobs from Redis List queue';

    public function __construct(
        private readonly ReportJobQueueService $queueService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Report queue worker started.');

        if ((bool) $this->option('once')) {
            $this->queueService->processNext();
            return self::SUCCESS;
        }

        while (true) {
            $this->queueService->processNext();
        }
    }
}
