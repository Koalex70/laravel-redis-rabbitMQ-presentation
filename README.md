# Laravel + PostgreSQL + Redis (Docker)

Production-minded local setup for the latest Laravel with:
- PHP-FPM (Laravel app container)
- Nginx (web server)
- PostgreSQL
- Redis

## Портфолио-питч

Учебно-практический проект, который наглядно показывает:
- как `Redis cache-aside` ускоряет чтение данных;
- как `Redis List` + worker разгружают API за счет фоновой обработки;
- как observability-стек (`Prometheus + Grafana + RedisInsight`) подтверждает эффект метриками и графиками.

Ключевая ценность проекта — воспроизводимый end-to-end сценарий:
`поднять стек -> прогнать нагрузку -> увидеть KPI на дашборде`.

## Что демонстрирует проект

- Архитектура `Laravel + PostgreSQL + Redis` для high-read/high-burst сценариев.
- Четкое разделение ответственности по MVC и сервисному слою.
- Контролируемые demo endpoint'ы для повторяемых прогонов.
- Нагрузочное тестирование через `k6` и фиксация результатов в артефактах.

## Project structure

```text
.
├── compose.yaml
├── .env.example
├── .dockerignore
├── docker
│   ├── nginx
│   │   └── default.conf
│   ├── php
│   │   ├── Dockerfile
│   │   └── conf.d
│   │       └── app.ini
│   └── scripts
│       └── entrypoint.sh
└── src
```

## Quick start

1. Create your local env file for Docker Compose:

```bash
cp .env.example .env
```

2. Start services:

```bash
docker compose up -d --build
```

3. Open app:
- [http://localhost:8080](http://localhost:8080)

On first startup, the `app` container will generate a fresh latest Laravel project into `src`.

## Useful commands

```bash
# app shell
docker compose exec app sh

# run migrations
docker compose exec app php artisan migrate

# run tests
docker compose exec app php artisan test

# stop stack
docker compose down
```

## Laravel environment notes

Inside Laravel (`src/.env`) ensure these values:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=laravel

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Шаг 0: цели демо и критерии успеха

### Контекст проекта

Делаем портфолио-проект на `Laravel + PostgreSQL + Redis`, который наглядно показывает:
- как кеш снижает задержку API;
- как очередь разгружает API при тяжелых задачах;
- как метрики подтверждают эффект на графиках.

### Состав первой версии (MVP)

- Backend: `Laravel` (API + worker-команда).
- Хранилище данных: `PostgreSQL`.
- Redis:
  - кеш (`cache-aside` с TTL);
  - очередь задач на `Redis List`.
- Наблюдаемость (полный вариант): метрики и дашборды (Prometheus + Grafana).
- Документация проекта: на русском языке.

### Демонстрационные сценарии

1. **Кеш**
   - Endpoint читает данные из Redis по ключу.
   - При `MISS` берет данные из PostgreSQL, кладет в кеш с TTL, возвращает ответ.
   - При `HIT` возвращает данные сразу из Redis.

2. **Очереди**
   - API принимает тяжелую задачу и быстро возвращает `jobId`.
   - Worker обрабатывает задачу в фоне из `Redis List`.
   - Клиент читает статус задачи отдельным endpoint.

### KPI и метрики (обязательные)

Показываем и фиксируем на дашборде:
- `cache_hit_rate` (%);
- `cache_hits_total` / `cache_misses_total`;
- latency API: `p50`, `p95`, `p99`;
- длительность `GET` при `HIT` и при `MISS`;
- время ответа `POST /jobs` (enqueue);
- глубина очереди (`queue_depth`);
- скорость обработки (`jobs_processed_per_sec`);
- количество успешных и ошибочных задач (`jobs_done_total`, `jobs_failed_total`).

### Целевые значения для демо

- `GET` с `HIT`: стабильно быстрее `MISS` (ориентир: < 30 ms на локальном стенде).
- `GET` с `MISS`: заметно медленнее из-за чтения из PostgreSQL.
- `POST /jobs`: быстрый ответ API (ориентир: < 100 ms на локальном стенде).
- При батче задач API остается отзывчивым, а очередь растет/снижается предсказуемо.

### Ограничения первой версии

- В первой версии не реализуем отказоустойчивые сценарии `retry/dead-letter`.
- Фокус на базовой наглядности кеша, очереди и метрик.

### Definition of Done для шага 0

Шаг 0 считается завершенным, когда:
- зафиксирован стек (`Laravel`, `PostgreSQL`, `Redis List`);
- определен состав MVP (API, worker, метрики, дашборды);
- согласован список обязательных KPI;
- зафиксированы целевые ориентиры по задержкам;
- зафиксированы границы первой версии (без retry/dead-letter);
- этот раздел добавлен в `README` и служит источником требований для шага 1.

## Шаг 1: каркас проекта и окружение

На этом шаге поднимаем инфраструктуру, чтобы перейти к реализации кеша и очередей:
- `app` (Laravel API);
- `worker` (заглушка, отдельный контейнер под будущий обработчик очереди);
- `web` (Nginx);
- `postgres`;
- `redis`;
- `redisinsight` (UI для визуальной проверки Redis).

### Что уже включает шаг 1

- Контейнер `worker` в `compose` на том же образе, что и `app`.
- `redisinsight` в `compose` (порт `5540` по умолчанию).
- Endpoint `GET /api/v1/health` (статус приложения).
- Endpoint `GET /api/v1/health/deps` (проверка `PostgreSQL` и `Redis` с latency).
- Подготовленная миграция `products` для следующего шага с кешем.
- Базовые демо-переменные окружения:
  - `CACHE_TTL_SECONDS=60`
  - `QUEUE_NAME=reports`

### Быстрый запуск шага 1

```bash
cp .env.example .env
docker compose up -d --build
```

### Проверка готовности окружения

```bash
# статусы контейнеров
docker compose ps

# запуск миграций
docker compose exec app php artisan migrate

# проверка health endpoint приложения
curl http://localhost:8080/api/v1/health

# проверка зависимостей (db + redis)
curl http://localhost:8080/api/v1/health/deps
```

Ожидаемый результат:
- `health` возвращает `status=ok`;
- `health/deps` возвращает `status=ok`, а в `checks` оба статуса `up`;
- миграции выполняются без ошибок.

### Definition of Done для шага 1

Шаг 1 считается завершенным, когда:
- `docker compose up -d --build` стабильно поднимает все сервисы;
- контейнеры `app`, `worker`, `web`, `postgres`, `redis`, `redisinsight` в состоянии `Up`;
- Laravel подключается к PostgreSQL и Redis;
- `GET /api/v1/health` и `GET /api/v1/health/deps` доступны и корректны;
- миграция таблицы `products` применяется успешно.

## Шаг 2: кеширование products (MVC + Redis cache-aside)

Реализован endpoint `GET /api/v1/products/{id}` в MVC-структуре:
- контроллер: `ProductController`;
- модель: `Product`;
- сервис кеша: `ProductCacheService`.

### Поведение endpoint

- Сначала выполняется чтение из Redis по ключу `cache:product:{id}`.
- При `HIT` данные возвращаются из Redis.
- При `MISS` данные читаются из PostgreSQL, сохраняются в Redis с TTL и возвращаются клиенту.
- Если продукт не найден, возвращается `404`.

### Заголовки для наглядной демонстрации

- `X-Cache: HIT|MISS`
- `X-Response-Time-Ms: <число>`

### Настройки демо

- `CACHE_TTL_SECONDS` — TTL кеша продукта (по умолчанию `60`).
- `CACHE_MISS_DELAY_MS` — искусственная задержка при MISS (по умолчанию `350`).

## Шаг 3: очереди и worker (Redis List + PostgreSQL)

Реализована постановка и обработка задач отчетов:
- очередь на `Redis List`;
- статус и история задач в `PostgreSQL` (`report_jobs`);
- фоновый обработчик в отдельном контейнере `worker`;
- retry и dead-letter;
- bulk enqueue;
- унифицированный формат ошибок API.

### API шага 3

- `POST /api/v1/jobs/report` — постановка одной задачи.
- `POST /api/v1/jobs/report/bulk` — постановка пакета задач.
- `GET /api/v1/jobs/{id}` — чтение текущего статуса задачи.

### Статусы задач

- `queued`
- `processing`
- `done`
- `failed`

### Retry и dead-letter

- Количество попыток регулируется `QUEUE_MAX_ATTEMPTS` (по умолчанию `3`).
- При ошибке и оставшихся попытках задача возвращается в очередь.
- После исчерпания попыток задача переводится в `failed` и пишется в dead-letter: `queue:reports:dead`.

### Worker

- Контейнер `worker` запускает команду:
  - `php artisan app:queue-worker`
- Внутри используется `BRPOP` по `queue:reports`.

### Настройки очередей

- `QUEUE_NAME=reports`
- `QUEUE_POP_TIMEOUT=5`
- `QUEUE_MAX_ATTEMPTS=3`
- `JOB_STATUS_TTL_SECONDS=86400`
- `JOB_PROCESSING_DELAY_MS=800`

### Формат ошибок API

Ошибки возвращаются в едином формате:

```json
{
  "error": {
    "code": "validation_error",
    "message": "Validation failed.",
    "details": {}
  }
}
```

## Шаг 4: наблюдаемость и дашборд

Подготовлена полная наблюдаемость на базе API-метрик:
- `Prometheus` (scrape `/api/v1/metrics/prometheus`);
- `Grafana` с автоматическим provisioning datasource и dashboard;
- дополнительные endpoint'ы метрик и demo-управления.

### Новые API endpoint'ы метрик

- `GET /api/v1/metrics/cache`
- `GET /api/v1/metrics/queue`
- `GET /api/v1/metrics/overview`
- `GET /api/v1/metrics/prometheus`

### Demo endpoint'ы управления

- `POST /api/v1/demo/cache/flush`
- `POST /api/v1/demo/metrics/reset`
- `POST /api/v1/demo/jobs/enqueue`

### Сервисы мониторинга

- Prometheus: `http://localhost:9090`
- Grafana: `http://localhost:3000` (`admin/admin`)
- RedisInsight: `http://localhost:5540`

### Переменные окружения шага 4

- `PROMETHEUS_PORT=9090`
- `PROMETHEUS_RETENTION=7d`
- `GRAFANA_PORT=3000`
- `GRAFANA_ADMIN_USER=admin`
- `GRAFANA_ADMIN_PASSWORD=admin`

### Запуск и проверка шага 4

```bash
# 1) поднять сервисы
docker compose up -d --build

# 2) проверить метрики API
curl http://localhost:8080/api/v1/metrics/cache
curl http://localhost:8080/api/v1/metrics/queue
curl http://localhost:8080/api/v1/metrics/overview
curl http://localhost:8080/api/v1/metrics/prometheus

# 3) проверить Prometheus target
curl http://localhost:9090/api/v1/targets
```

Ожидаемое состояние:
- Prometheus target `laravel_metrics` в статусе `up`;
- Grafana доступна на `http://localhost:3000`;
- dashboard `Redis Demo Overview` загружен автоматически.

### Быстрый demo-flow

```bash
# очистить метрики и кеш
curl -X POST http://localhost:8080/api/v1/demo/metrics/reset
curl -X POST http://localhost:8080/api/v1/demo/cache/flush

# показать MISS -> HIT
curl http://localhost:8080/api/v1/products/1
curl http://localhost:8080/api/v1/products/1

# создать batch задач
curl -X POST http://localhost:8080/api/v1/demo/jobs/enqueue
```

## Шаг 5: нагрузочное тестирование и фиксация KPI

Шаг 5 фиксирует фактические показатели под нагрузкой и подтверждает целевые KPI.

### Что используется

- `k6` в Docker-образе `grafana/k6`;
- готовые сценарии:
  - `load-tests/k6-cache.js`
  - `load-tests/k6-jobs.js`
- скрипт запуска:
  - `load-tests/run-step5.ps1`

### Подготовка перед тестом

```bash
# 1) убедиться, что стек запущен
docker compose up -d --build

# 2) применить миграции
docker compose exec app php artisan migrate --force

# 3) подготовить продукт для cache-теста (id=1)
docker compose exec app php artisan tinker --execute="App\\Models\\Product::updateOrCreate(['id'=>1], ['name'=>'Demo product','sku'=>'SKU-DEMO-001','description'=>'Product for load test','price_cents'=>19900,'stock'=>100]);"
```

### Запуск нагрузочного прогона

```bash
powershell -ExecutionPolicy Bypass -File .\load-tests\run-step5.ps1
```

Скрипт создаст summary-файлы:
- `load-tests/results/cache-summary.json`
- `load-tests/results/jobs-summary.json`

### Как интерпретировать результаты

- `cache`:
  - `http_req_duration p(95)` должен заметно снижаться после прогрева;
  - в метриках API растет `cache_hits_total`, hit rate стремится вверх.
- `jobs`:
  - `POST /jobs/report` стабильно отвечает `202`;
  - worker разгребает очередь, `jobs_processed_total` растет;
  - API остается отзывчивым при росте queue depth.

### KPI, которые фиксируем в отчете шага 5

- `GET /products/{id}`:
  - `MISS` latency (с начальной задержкой),
  - `HIT` latency,
  - разница `MISS -> HIT`.
- `POST /jobs/report`:
  - `p50/p95` времени ответа;
  - доля ошибок (`http_req_failed`).
- Очереди:
  - пик `queue_depth`;
  - `jobs_processed_total`, `jobs_failed_total`, `jobs_retried_total`.

### Базовый результат прогона (локальный стенд)

Пример замера после выполнения `load-tests/run-step5.ps1`:

- Cache load (`k6-cache.js`):
  - `http_req_duration p(95) ~= 912ms`
  - `http_req_duration avg ~= 638ms`
  - `http_req_failed = 0%`
- Jobs enqueue load (`k6-jobs.js`):
  - `http_req_duration p(95) ~= 966ms`
  - `http_req_duration avg ~= 757ms`
  - `http_req_failed = 0%`

Snapshot очереди после jobs-прогона (`/api/v1/metrics/overview`):
- `queue.depth = 260`
- `jobs.enqueued_total = 146`
- `jobs.processed_total = 168`
- `jobs.failed_total = 0`

## Чеклист для портфолио-скриншотов

Рекомендуемый набор скриншотов для README/резюме:

- Grafana: `Redis Demo Overview` (все панели в одном кадре).
- Grafana: `Cache Hit Rate` до и после прогрева.
- Grafana: `Queue Depth` при batch enqueue и последующем дренировании.
- Grafana: `Jobs Counters` (processed/failed/retried).
- Prometheus targets: статус `laravel_metrics = up`.
- RedisInsight: ключи `cache:product:*`, `queue:reports`, `queue:reports:dead`.
- API ответ `GET /api/v1/metrics/overview` после прогона.
- Артефакты `load-tests/results/*.json` (фрагмент с p95/avg/failed rate).

Минимальный “demo proof pack”:
1) Скрин Grafana overview  
2) Скрин Prometheus target up  
3) Фрагмент k6 summary с p95  
4) Скрин queue/dead-letter в RedisInsight
