# Deploying FreshCart (Bagisto) to AWS EC2

Server: Ubuntu EC2, project cloned at `~/FreshCart-Bagisto-`.
Stack: `docker-compose.prod.yml` (Caddy → nginx → PHP-FPM, MySQL, Redis, Elasticsearch, scheduler).

## 0. Before you start

- **Instance size:** at least 4 GB RAM (`t3.medium`). The build (composer + npm) plus
  Elasticsearch won't fit in 1–2 GB. On a smaller instance, add swap (step 2).
- **Security group inbound rules:** 22 (your IP only), 80 and 443 (anywhere).
- **Elastic IP** attached to the instance, so the address doesn't change.

## 1. On your PC: push the Docker files

The `Dockerfile`, `docker/` folder and compose files must be in Git, or the server can't build the image.

```powershell
cd C:\laragon\www\bagisto-2.4
git add Dockerfile .dockerignore docker docker-compose.yml docker-compose.prod.yml .env.production.example .gitignore DEPLOY-AWS.md
git commit -m "Add Docker production setup for AWS"
git push
```

## 2. On the server: install Docker (skip if `docker --version` works)

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker ubuntu
exit   # log in again so the group change applies
```

Optional swap (recommended on instances with less than 4 GB):

```bash
sudo fallocate -l 4G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

## 3. On the server: pull and configure

```bash
cd ~/FreshCart-Bagisto-
git pull
cp .env.production.example .env
nano .env
```

Fill in every `CHANGE_ME`:
- `APP_KEY`: copy it from your local `.env`.
- `APP_URL`: `http://YOUR_ELASTIC_IP` for now.
- `DB_PASSWORD`, `DB_ROOT_PASSWORD`: new strong passwords.
- `MAIL_FROM_ADDRESS`

## 4. On your PC: export the database and uploaded images

Start Docker Desktop and the local stack (`docker compose up -d`), then:

```powershell
cd C:\laragon\www\bagisto-2.4
docker compose exec mysql sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --no-tablespaces "$MYSQL_DATABASE" > /tmp/backup.sql'
docker compose cp mysql:/tmp/backup.sql ./backup.sql
docker compose cp app:/var/www/html/storage/app/public ./storage-public
tar -czf storage-public.tgz -C storage-public .
scp -i C:\path\to\your-key.pem backup.sql storage-public.tgz ubuntu@YOUR_ELASTIC_IP:~/FreshCart-Bagisto-/
```

(`docker compose cp` avoids PowerShell's `>` redirect, which saves files as UTF-16 and breaks the SQL import.)

## 5. On the server: build, import, start

```bash
cd ~/FreshCart-Bagisto-

# Build the images (takes several minutes the first time)
docker compose build

# Start only MySQL and import the database
docker compose up -d mysql
docker compose cp backup.sql mysql:/tmp/backup.sql
docker compose exec mysql sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /tmp/backup.sql'

# Start everything (runs migrations and caches config)
docker compose up -d

# Restore uploaded images
mkdir -p storage-public && tar -xzf storage-public.tgz -C storage-public
docker compose cp storage-public/. app:/var/www/html/storage/app/public/
docker compose exec -u root app chown -R www-data:www-data /var/www/html/storage

docker compose ps
```

Open `http://YOUR_ELASTIC_IP` (storefront) and `http://YOUR_ELASTIC_IP/admin`.

Then delete the dump from the server: `rm backup.sql storage-public.tgz && rm -r storage-public`.

## 6. Domain + HTTPS

1. At your DNS provider, add an **A record**: `shop.yourdomain.com → YOUR_ELASTIC_IP`.
2. In the server's `.env`:
   ```
   SITE_ADDRESS=shop.yourdomain.com
   APP_URL=https://shop.yourdomain.com
   ```
3. `docker compose up -d --force-recreate app scheduler caddy`

Caddy gets the Let's Encrypt certificate by itself within a few seconds of the DNS resolving.

## Updating after code changes

```bash
cd ~/FreshCart-Bagisto-
git pull
docker compose up -d --build
```

## Troubleshooting

| Symptom | Check |
|---|---|
| A container keeps restarting | `docker compose logs -f app` (or `mysql`, `elasticsearch`) |
| Laravel error page / 500 | `docker compose exec app tail -n 100 storage/logs/laravel.log` |
| Changed `.env` but nothing changed | `docker compose up -d --force-recreate app scheduler` (config is cached at start) |
| Server freezes, Elasticsearch exits with code 137 | Out of memory: bigger instance or add swap |
| Images 404 | Step 5 media restore wasn't run |
| Site unreachable | Security group missing port 80/443 |

## Backups

```bash
docker compose exec mysql sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' | gzip > ~/backup-$(date +%F).sql.gz
```
