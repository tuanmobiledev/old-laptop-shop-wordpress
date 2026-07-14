# Dokploy deployment

## Module hien co

- `laprevive-web`: frontend React/Vite, build thanh static files va serve bang Nginx.
- `laprevive-db`: PostgreSQL 16 dong goi rieng bang Dockerfile trong `db/`, co volume rieng `laprevive_pgdata`.
- Web container port: `80`.
- DB container port: `5432`, host mac dinh khi chay compose truc tiep: `${DB_PORT:-15432}`.
- Host mac dinh cho web khi chay compose truc tiep: `${APP_PORT:-18080}`.
- Server muc tieu: `coder-tuan`.

## Cau hinh tren Dokploy

1. Vao server `coder-tuan`.
2. Tao app moi: `laprevive-web`.
3. Chon deploy bang `Docker Compose`.
4. Tro source toi project/repository nay.
5. Compose file: `docker-compose.yml`.
6. Dat bien moi truong production, toi thieu `POSTGRES_PASSWORD` manh, khong dung gia tri default.
7. Gan domain/subdomain vao service `laprevive-web`, port noi bo `80`.
8. Khong public service `laprevive-db` ra internet neu khong can; web noi bo dung host `laprevive-db:5432`.
9. Deploy.

## Chay thu tren server bang CLI

```bash
cd /path/to/old-laptop-shop
docker compose up -d --build
docker compose ps
curl -I http://127.0.0.1:${APP_PORT:-18080}
```

## Tach them module sau nay

Khi them backend/admin/API, tao them service rieng trong `docker-compose.yml`, vi du:

```yaml
services:
  laprevive-api:
    build:
      context: ./api
    restart: unless-stopped
    environment:
      NODE_ENV: production
```

Sau do tren Dokploy route tung service theo domain rieng, vi du `api.example.com` vao `laprevive-api` va `shop.example.com` vao `laprevive-web`.
