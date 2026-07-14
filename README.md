# LapRevive Web

Web ban laptop cu, phu kien laptop va dich vu sua chua laptop. Project dung React + Vite, dong goi Docker de deploy tren Dokploy.

## Chay local

```bash
npm install
npm run dev
```

## Build kiem tra

```bash
npm run build
```

## Chay bang Docker

```bash
docker compose up -d --build
```

Mac dinh app map ra cong `18080` tren host va cong `80` trong container. Co the doi bang bien `APP_PORT`.

## Deploy tren Dokploy / coder-tuan

Xem them checklist chi tiet tai `DOKPLOY.md`.

1. Tao application moi tren server `coder-tuan`.
2. Chon kieu deploy `Docker Compose`.
3. Tro source ve thu muc project nay hoac repository chua project.
4. Su dung file `docker-compose.yml` co san.
5. Trong Dokploy, route domain/subdomain vao service `laprevive-web`, container port `80`.
6. Neu can doi cong host khi chay truc tiep bang compose, dat bien `APP_PORT`, vi du `APP_PORT=8081 docker compose up -d --build`.

## Mo rong nganh hang

Them category va product moi trong `src/data.js`. Vi du them dien thoai:

```js
{ id: 'dien-thoai', label: 'Dien thoai' }
```

Sau do them product co `category: 'dien-thoai'`.
