# Docker Development Environment

Two Docker configurations are available for ext-mgr development:

## 1. NGINX + PHP-FPM (Default) - Recommended

Matches the moOde production stack (NGINX + PHP-FPM).

```bash
# Build and start
docker compose up --build

# Access
http://localhost:8080/ext-mgr.php
```

**Features:**

- Uses NGINX + PHP-FPM (same as moOde production)
- Supervisor manages both services
- Includes Node.js for Gulp builds
- SQLite database with moOde schema
- Live source file linking (changes reflect immediately)

## 2. Apache (Alternative) - Simpler

Lighter setup for quick testing. Note: moOde production does NOT use Apache.

```bash
# Build and start with Apache profile
docker compose --profile apache up --build
```

## Volume Mounts

| Volume | Purpose |
|--------|---------|
| `moode-extensions` | Persistent extensions storage |
| `moode-db` | SQLite database persistence |
| `./` (read-only) | Source files mounted to `/ext-mgr-src` |

## File Structure

```
docker/
├── nginx-fpm/           # NGINX + PHP-FPM setup (default)
│   ├── Dockerfile
│   ├── entrypoint.sh
│   ├── nginx/
│   │   ├── nginx.conf
│   │   ├── default.conf
│   │   └── snippets/fastcgi-php.conf
│   ├── php-fpm/
│   │   ├── www.conf
│   │   └── php.ini
│   └── supervisord.conf
├── apache/              # Apache setup (alternative)
│   ├── Dockerfile
│   └── entrypoint.sh
└── moode-dev/           # Legacy (deprecated)
```

## Development Workflow

1. Make changes to source files locally
2. Changes are automatically linked in container
3. Refresh browser to see updates

## Rebuild After Docker Changes

```bash
# Force rebuild
docker compose down
docker compose up --build --force-recreate

# Clean volumes (WARNING: deletes extensions/database)
docker compose down -v
```

## Troubleshooting

**Check logs:**

```bash
docker compose logs -f
```

**Enter container:**

```bash
docker compose exec moode-nginx bash
```

**Check PHP-FPM:**

```bash
docker compose exec moode-nginx php -v
docker compose exec moode-nginx php -m | grep -i sqlite
```

**Check database:**

```bash
docker compose exec moode-nginx sqlite3 /var/local/www/db/moode-sqlite3.db ".tables"
```
