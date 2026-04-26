<?php

namespace App\Services\Queue;

use App\Models\BenchmarkRun;
use App\Support\BenchmarkRunStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class BenchmarkRunQueueService
{
    public function enqueue(string $type): BenchmarkRun
    {
        $run = BenchmarkRun::query()->create([
            'type' => $type,
            'status' => BenchmarkRunStatus::QUEUED,
        ]);

        Redis::lpush($this->queueKey(), json_encode(['run_id' => $run->id]));

        return $run;
    }

    public function processNext(): bool
    {
        $rawItem = Redis::brpop([$this->queueKey()], 1);

        if (!is_array($rawItem) || count($rawItem) !== 2) {
            return false;
        }

        $payload = json_decode($rawItem[1], true);
        if (!is_array($payload) || !isset($payload['run_id'])) {
            return false;
        }

        /** @var BenchmarkRun|null $run */
        $run = BenchmarkRun::query()->find($payload['run_id']);
        if ($run === null) {
            return false;
        }

        $run->status = BenchmarkRunStatus::RUNNING;
        $run->started_at = now();
        $run->save();

        try {
            $summary = $run->type === 'cache'
                ? $this->runCacheBenchmark()
                : $this->runJobsBenchmark();

            $run->status = BenchmarkRunStatus::DONE;
            $run->summary = $summary;
            $run->error = null;
            $run->finished_at = now();
            $run->save();
        } catch (\Throwable $exception) {
            $run->status = BenchmarkRunStatus::FAILED;
            $run->error = $exception->getMessage();
            $run->finished_at = now();
            $run->save();
        }

        return true;
    }

    public function latest(int $limit = 10)
    {
        return BenchmarkRun::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function runCacheBenchmark(): array
    {
        Http::post($this->baseUrl().'/api/v1/demo/cache/flush');
        Http::post($this->baseUrl().'/api/v1/demo/metrics/reset');

        // Warm-up and measure a short synthetic run for UI
        Http::get($this->baseUrl().'/api/v1/products/1');
        $durations = [];
        $errors = 0;

        for ($i = 0; $i < 25; $i++) {
            $startedAt = microtime(true);
            $response = Http::timeout(10)->get($this->baseUrl().'/api/v1/products/1');
            $durations[] = (microtime(true) - $startedAt) * 1000;
            if (!$response->successful()) {
                $errors++;
            }
            usleep(150000);
        }

        return $this->buildSummary($durations, $errors, 'cache');
    }

    private function runJobsBenchmark(): array
    {
        Http::post($this->baseUrl().'/api/v1/demo/metrics/reset');
        $durations = [];
        $errors = 0;

        for ($i = 0; $i < 20; $i++) {
            $startedAt = microtime(true);
            $response = Http::timeout(10)->post($this->baseUrl().'/api/v1/jobs/report', [
                'report_type' => 'sales',
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
                'user_id' => 1,
            ]);
            $durations[] = (microtime(true) - $startedAt) * 1000;
            if ($response->status() !== 202) {
                $errors++;
            }
            usleep(120000);
        }

        return $this->buildSummary($durations, $errors, 'jobs');
    }

    private function buildSummary(array $durations, int $errors, string $type): array
    {
        sort($durations);
        $count = count($durations);
        $avg = $count > 0 ? array_sum($durations) / $count : 0.0;
        $p95Index = $count > 0 ? (int) ceil(0.95 * $count) - 1 : 0;
        $p95 = $count > 0 ? $durations[max(0, $p95Index)] : 0.0;

        return [
            'type' => $type,
            'requests' => $count,
            'errors' => $errors,
            'error_rate' => $count > 0 ? round(($errors / $count) * 100, 2) : 0.0,
            'avg_ms' => round($avg, 2),
            'p95_ms' => round($p95, 2),
            'finished_at' => now()->toIso8601String(),
        ];
    }

    private function queueKey(): string
    {
        return 'queue:benchmarks';
    }

    private function baseUrl(): string
    {
        return (string) env('BENCHMARK_BASE_URL', 'http://web');
    }
}
