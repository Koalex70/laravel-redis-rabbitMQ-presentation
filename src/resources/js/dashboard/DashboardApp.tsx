import React, { useEffect, useMemo, useState } from "react";

type MetricsOverview = {
  cache: { hits_total: number; misses_total: number; hit_rate: number };
  queue: {
    depth: number;
    dead_letter_depth: number;
    jobs: {
      enqueued_total: number;
      processed_total: number;
      failed_total: number;
      retried_total: number;
      invalid_payload_total: number;
      missing_in_db_total: number;
    };
  };
  api_latency: Record<string, { count: number; sum_ms: number; avg_ms: number }>;
};

type BenchmarkRun = {
  id: string;
  type: "cache" | "jobs";
  status: string;
  summary: null | {
    requests: number;
    errors: number;
    error_rate: number;
    avg_ms: number;
    p95_ms: number;
  };
  error: string | null;
  created_at: string;
  started_at: string | null;
  finished_at: string | null;
};

const POLL_INTERVAL_MS = 2000;

async function fetchJson<T>(url: string, init?: RequestInit): Promise<T> {
  const response = await fetch(url, init);
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}: ${url}`);
  }
  return response.json() as Promise<T>;
}

export default function DashboardApp() {
  const [metrics, setMetrics] = useState<MetricsOverview | null>(null);
  const [runs, setRuns] = useState<BenchmarkRun[]>([]);
  const [isBusy, setIsBusy] = useState<Record<string, boolean>>({});
  const [error, setError] = useState<string>("");
  const [cacheWarmAt, setCacheWarmAt] = useState<string | null>(null);

  const loadData = async () => {
    try {
      const [metricsResponse, runsResponse] = await Promise.all([
        fetchJson<{ data: MetricsOverview }>("/api/v1/metrics/overview"),
        fetchJson<{ data: BenchmarkRun[] }>("/api/v1/tests/runs?limit=10"),
      ]);
      setMetrics(metricsResponse.data);
      setRuns(runsResponse.data);
      setError("");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Не удалось обновить данные.");
    }
  };

  useEffect(() => {
    loadData();
    const timer = window.setInterval(loadData, POLL_INTERVAL_MS);
    return () => window.clearInterval(timer);
  }, []);

  const setBusy = (key: string, value: boolean) =>
    setIsBusy((prev) => ({ ...prev, [key]: value }));

  const performAction = async (key: string, url: string, body?: object) => {
    try {
      setBusy(key, true);
      await fetchJson(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: body ? JSON.stringify(body) : undefined,
      });
      await loadData();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Ошибка выполнения действия.");
    } finally {
      setBusy(key, false);
    }
  };

  const cards = useMemo(
    () => [
      {
        label: "Cache Hit Rate",
        value: metrics ? `${metrics.cache.hit_rate.toFixed(2)}%` : "—",
      },
      {
        label: "Queue Depth",
        value: metrics ? String(metrics.queue.depth) : "—",
      },
      {
        label: "Processed",
        value: metrics ? String(metrics.queue.jobs.processed_total) : "—",
      },
      {
        label: "Failed",
        value: metrics ? String(metrics.queue.jobs.failed_total) : "—",
      },
      {
        label: "Retried",
        value: metrics ? String(metrics.queue.jobs.retried_total) : "—",
      },
      {
        label: "Dead Letter",
        value: metrics ? String(metrics.queue.dead_letter_depth) : "—",
      },
    ],
    [metrics],
  );

  const activeRun = useMemo(
    () => runs.find((run) => run.status === "queued" || run.status === "running") ?? null,
    [runs],
  );

  const cacheState = useMemo(() => {
    if (!metrics) {
      return { label: "Нет данных", tone: "text-slate-300", bg: "bg-slate-800/60" };
    }

    const { hits_total: hits, misses_total: misses, hit_rate: hitRate } = metrics.cache;
    const hasTraffic = hits + misses > 0;

    if (!hasTraffic) {
      return { label: "Холодный (нет запросов)", tone: "text-slate-300", bg: "bg-slate-800/60" };
    }

    const warmThresholdReached = misses > 0 && hitRate >= 80 && hits >= 3;
    if (warmThresholdReached) {
      return { label: "Прогрет", tone: "text-emerald-300", bg: "bg-emerald-900/30" };
    }

    return { label: "Прогревается", tone: "text-amber-300", bg: "bg-amber-900/30" };
  }, [metrics]);

  useEffect(() => {
    if (!metrics) {
      return;
    }

    const { hits_total: hits, misses_total: misses, hit_rate: hitRate } = metrics.cache;
    const warmThresholdReached = misses > 0 && hitRate >= 80 && hits >= 3;
    const wasReset = hits === 0 && misses === 0;

    if (wasReset) {
      setCacheWarmAt(null);
      return;
    }

    if (warmThresholdReached && !cacheWarmAt) {
      setCacheWarmAt(new Date().toISOString());
    }
  }, [metrics, cacheWarmAt]);

  useEffect(() => {
    if (activeRun) {
      const element = document.getElementById("active-run-panel");
      element?.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  }, [activeRun?.id]);

  return (
    <div className="mx-auto max-w-7xl p-6">
      <header className="mb-6">
        <h1 className="text-3xl font-bold">Redis Demo Dashboard</h1>
        <p className="mt-1 text-slate-300">Почти realtime обновление каждые 2 секунды</p>
      </header>

      {error ? (
        <div className="mb-6 rounded border border-rose-500 bg-rose-950/30 px-4 py-3 text-rose-200">
          {error}
        </div>
      ) : null}

      <section className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
        {cards.map((card) => (
          <article key={card.label} className="rounded border border-slate-700 bg-slate-900 p-4">
            <p className="text-xs uppercase tracking-wide text-slate-400">{card.label}</p>
            <p className="mt-2 text-2xl font-semibold">{card.value}</p>
          </article>
        ))}
      </section>

      <section className="mb-6 rounded border border-slate-700 bg-slate-900 p-4">
        <h2 className="mb-3 text-lg font-semibold">Состояние прогрева кеша</h2>
        <div className={`rounded border border-slate-700 px-4 py-3 ${cacheState.bg}`}>
          <p className={`text-base font-semibold ${cacheState.tone}`}>{cacheState.label}</p>
          <p className="mt-1 text-sm text-slate-300">
            Критерий прогрева: hit rate не ниже 80%, минимум 3 HIT и хотя бы 1 MISS.
          </p>
          <p className="mt-1 text-sm text-slate-300">
            Момент прогрева: {cacheWarmAt ? formatDateTime(cacheWarmAt) : "еще не достигнут"}
          </p>
        </div>
      </section>

      <section className="mb-6 rounded border border-slate-700 bg-slate-900 p-4">
        <h2 className="mb-3 text-lg font-semibold">Управление тестами</h2>
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3 lg:grid-cols-5">
          <ActionButton
            label="Сбросить метрики"
            busy={!!isBusy.reset}
            onClick={() => performAction("reset", "/api/v1/demo/metrics/reset")}
          />
          <ActionButton
            label="Очистить кеш"
            busy={!!isBusy.flush}
            onClick={() => performAction("flush", "/api/v1/demo/cache/flush")}
          />
          <ActionButton
            label="Запустить cache test"
            busy={!!isBusy.cacheTest}
            onClick={() => performAction("cacheTest", "/api/v1/tests/cache/run")}
          />
          <ActionButton
            label="Запустить jobs test"
            busy={!!isBusy.jobsTest}
            onClick={() => performAction("jobsTest", "/api/v1/tests/jobs/run")}
          />
          <ActionButton
            label="Bulk enqueue (100)"
            busy={!!isBusy.bulk}
            onClick={() => performAction("bulk", "/api/v1/demo/jobs/enqueue", { count: 100 })}
          />
        </div>
      </section>

      <section id="active-run-panel" className="mb-6 rounded border border-slate-700 bg-slate-900 p-4">
        <h2 className="mb-3 text-lg font-semibold">Активный запуск</h2>
        {activeRun ? (
          <div className="space-y-3">
            <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
              <MetricLine label="ID" value={activeRun.id} />
              <MetricLine label="Тип" value={activeRun.type} />
              <MetricLine label="Статус" value={activeRun.status} />
              <MetricLine
                label="Длительность"
                value={formatDuration(activeRun.started_at, activeRun.finished_at)}
              />
            </div>
            <ProgressBar status={activeRun.status} />
          </div>
        ) : (
          <p className="text-slate-400">Сейчас нет активных запусков.</p>
        )}
      </section>

      <section className="rounded border border-slate-700 bg-slate-900 p-4">
        <h2 className="mb-3 text-lg font-semibold">История запусков (последние 10)</h2>
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <thead>
              <tr className="border-b border-slate-700 text-left text-slate-300">
                <th className="px-3 py-2">Тип</th>
                <th className="px-3 py-2">Статус</th>
                <th className="px-3 py-2">P95 (ms)</th>
                <th className="px-3 py-2">AVG (ms)</th>
                <th className="px-3 py-2">Ошибки</th>
                <th className="px-3 py-2">Старт</th>
                <th className="px-3 py-2">Финиш</th>
                <th className="px-3 py-2">Длительность</th>
                <th className="px-3 py-2">Детали ошибки</th>
              </tr>
            </thead>
            <tbody>
              {runs.map((run) => (
                <tr key={run.id} className="border-b border-slate-800">
                  <td className="px-3 py-2">{run.type}</td>
                  <td className="px-3 py-2">{run.status}</td>
                  <td className="px-3 py-2">{run.summary?.p95_ms ?? "—"}</td>
                  <td className="px-3 py-2">{run.summary?.avg_ms ?? "—"}</td>
                  <td className="px-3 py-2">{run.summary?.errors ?? "—"}</td>
                  <td className="px-3 py-2">{formatDateTime(run.started_at)}</td>
                  <td className="px-3 py-2">{formatDateTime(run.finished_at)}</td>
                  <td className="px-3 py-2">{formatDuration(run.started_at, run.finished_at)}</td>
                  <td className="max-w-[280px] px-3 py-2 text-rose-300">
                    {run.error ? run.error : "—"}
                  </td>
                </tr>
              ))}
              {runs.length === 0 ? (
                <tr>
                  <td className="px-3 py-4 text-slate-400" colSpan={9}>
                    Пока нет запусков.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

function ProgressBar(props: { status: string }) {
  const progress = props.status === "queued" ? 20 : props.status === "running" ? 70 : 100;
  const animated = props.status === "queued" || props.status === "running";

  return (
    <div>
      <div className="mb-1 flex items-center justify-between text-xs text-slate-400">
        <span>Прогресс выполнения</span>
        <span>{progress}%</span>
      </div>
      <div className="h-2 overflow-hidden rounded bg-slate-800">
        <div
          className={`h-full rounded bg-indigo-500 transition-all duration-700 ${animated ? "animate-pulse" : ""}`}
          style={{ width: `${progress}%` }}
        />
      </div>
    </div>
  );
}

function MetricLine(props: { label: string; value: string }) {
  return (
    <div className="rounded border border-slate-700 bg-slate-950/40 px-3 py-2">
      <p className="text-[11px] uppercase tracking-wide text-slate-400">{props.label}</p>
      <p className="mt-1 truncate text-sm">{props.value}</p>
    </div>
  );
}

function formatDateTime(value: string | null): string {
  if (!value) return "—";
  return new Date(value).toLocaleString("ru-RU");
}

function formatDuration(startedAt: string | null, finishedAt: string | null): string {
  if (!startedAt) return "—";

  const start = new Date(startedAt).getTime();
  const end = finishedAt ? new Date(finishedAt).getTime() : Date.now();
  const diff = Math.max(0, end - start);

  if (diff < 1000) {
    return `${diff} мс`;
  }

  const seconds = Math.floor(diff / 1000);
  const ms = diff % 1000;
  return `${seconds}.${Math.floor(ms / 10)
    .toString()
    .padStart(2, "0")} c`;
}

function ActionButton(props: { label: string; busy: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={props.onClick}
      disabled={props.busy}
      className="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
    >
      {props.busy ? "Выполняется..." : props.label}
    </button>
  );
}
