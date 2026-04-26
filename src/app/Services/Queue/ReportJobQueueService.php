<?php

namespace App\Services\Queue;

use App\Models\ReportJob;
use App\Support\ReportJobStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

class ReportJobQueueService
{
    public function queueNamePublic(): string
    {
        return $this->queueName();
    }

    public function enqueue(array $payload): ReportJob
    {
        $queueName = $this->queueName();
        $job = ReportJob::query()->create([
            'queue' => $queueName,
            'status' => ReportJobStatus::QUEUED,
            'attempts' => 0,
            'payload' => $payload,
            'queued_at' => now(),
        ]);

        Redis::lpush($this->queueKey($queueName), json_encode(['job_id' => $job->id]));
        Redis::incr('metrics:jobs:enqueued');
        $this->storeStatusInRedis($job);

        return $job;
    }

    public function processNext(): bool
    {
        $queueName = $this->queueName();
        $rawItem = Redis::brpop([$this->queueKey($queueName)], $this->popTimeout());

        if (!is_array($rawItem) || count($rawItem) !== 2) {
            return false;
        }

        $payload = json_decode($rawItem[1], true);
        if (!is_array($payload) || !isset($payload['job_id'])) {
            Redis::incr('metrics:jobs:invalid_payload');
            return false;
        }

        /** @var ReportJob|null $job */
        $job = ReportJob::query()->find($payload['job_id']);
        if ($job === null) {
            Redis::incr('metrics:jobs:missing_in_db');
            return false;
        }

        if (in_array($job->status, [ReportJobStatus::DONE, ReportJobStatus::FAILED], true)) {
            return false;
        }

        $job->status = ReportJobStatus::PROCESSING;
        $job->started_at = now();
        $job->save();
        $this->storeStatusInRedis($job);

        try {
            $result = $this->simulateReportGeneration($job->payload);
            $job->status = ReportJobStatus::DONE;
            $job->result = $result;
            $job->error = null;
            $job->finished_at = now();
            $job->save();

            Redis::incr('metrics:jobs:processed');
            $this->storeStatusInRedis($job);
            return true;
        } catch (\Throwable $exception) {
            $attempts = $job->attempts + 1;
            $job->attempts = $attempts;
            $job->error = $exception->getMessage();

            if ($attempts < $this->maxAttempts()) {
                $job->status = ReportJobStatus::QUEUED;
                $job->started_at = null;
                $job->finished_at = null;
                $job->save();

                Redis::lpush($this->queueKey($queueName), json_encode(['job_id' => $job->id]));
                Redis::incr('metrics:jobs:retried');
                $this->storeStatusInRedis($job);
                return true;
            }

            $job->status = ReportJobStatus::FAILED;
            $job->finished_at = now();
            $job->save();

            Redis::lpush($this->deadLetterKey($queueName), json_encode([
                'job_id' => $job->id,
                'error' => $job->error,
                'failed_at' => Carbon::now()->toIso8601String(),
            ]));
            Redis::incr('metrics:jobs:failed');
            $this->storeStatusInRedis($job);
            return true;
        }
    }

    public function queueDepth(): int
    {
        return (int) Redis::llen($this->queueKey($this->queueName()));
    }

    public function deadLetterDepth(): int
    {
        return (int) Redis::llen($this->deadLetterKey($this->queueName()));
    }

    public function metricsSnapshot(): array
    {
        return [
            'enqueued_total' => (int) Redis::get('metrics:jobs:enqueued'),
            'processed_total' => (int) Redis::get('metrics:jobs:processed'),
            'failed_total' => (int) Redis::get('metrics:jobs:failed'),
            'retried_total' => (int) Redis::get('metrics:jobs:retried'),
            'invalid_payload_total' => (int) Redis::get('metrics:jobs:invalid_payload'),
            'missing_in_db_total' => (int) Redis::get('metrics:jobs:missing_in_db'),
        ];
    }

    public function resetMetrics(): void
    {
        Redis::del('metrics:jobs:enqueued');
        Redis::del('metrics:jobs:processed');
        Redis::del('metrics:jobs:failed');
        Redis::del('metrics:jobs:retried');
        Redis::del('metrics:jobs:invalid_payload');
        Redis::del('metrics:jobs:missing_in_db');
    }

    private function simulateReportGeneration(array $payload): array
    {
        $delayMs = max(0, (int) env('JOB_PROCESSING_DELAY_MS', 800));
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        if (($payload['force_fail'] ?? false) === true) {
            throw new \RuntimeException('Report generation failed (forced for demo).');
        }

        return [
            'report_id' => 'report-'.bin2hex(random_bytes(6)),
            'generated_at' => now()->toIso8601String(),
            'report_type' => (string) ($payload['report_type'] ?? 'unknown'),
        ];
    }

    private function storeStatusInRedis(ReportJob $job): void
    {
        $key = "job:{$job->id}";
        Redis::hset($key, 'id', $job->id);
        Redis::hset($key, 'status', $job->status);
        Redis::hset($key, 'attempts', (string) $job->attempts);
        Redis::hset($key, 'queue', $job->queue);
        Redis::hset($key, 'error', (string) ($job->error ?? ''));
        Redis::hset($key, 'updated_at', now()->toIso8601String());
        Redis::expire($key, $this->statusTtlSeconds());
    }

    private function queueName(): string
    {
        return (string) env('QUEUE_NAME', 'reports');
    }

    private function queueKey(string $queueName): string
    {
        return "queue:{$queueName}";
    }

    private function deadLetterKey(string $queueName): string
    {
        return "queue:{$queueName}:dead";
    }

    private function popTimeout(): int
    {
        return max(1, (int) env('QUEUE_POP_TIMEOUT', 5));
    }

    private function maxAttempts(): int
    {
        return max(1, (int) env('QUEUE_MAX_ATTEMPTS', 3));
    }

    private function statusTtlSeconds(): int
    {
        return max(60, (int) env('JOB_STATUS_TTL_SECONDS', 86400));
    }
}
