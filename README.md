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
