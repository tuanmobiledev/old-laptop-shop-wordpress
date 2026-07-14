# Multi-stage build for Dokploy or any Docker host.
FROM node:22-alpine AS build

WORKDIR /app
ARG VITE_GA4_MEASUREMENT_ID=
ENV VITE_GA4_MEASUREMENT_ID=$VITE_GA4_MEASUREMENT_ID
ARG VITE_ADMIN_TOKEN=
ENV VITE_ADMIN_TOKEN=$VITE_ADMIN_TOKEN
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM build AS api

EXPOSE 3000
CMD ["node", "server/newsletter-api.js"]

FROM nginx:1.27-alpine AS runtime

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
  CMD wget -qO- http://127.0.0.1/ >/dev/null || exit 1

CMD ["nginx", "-g", "daemon off;"]
