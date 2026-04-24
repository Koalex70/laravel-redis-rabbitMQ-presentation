# Laravel + PostgreSQL + Redis (Docker)

Production-minded local setup for the latest Laravel with:
- PHP-FPM (Laravel app container)
- Nginx (web server)
- PostgreSQL
- Redis

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
