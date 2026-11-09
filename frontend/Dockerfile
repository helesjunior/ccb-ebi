# Imagem de produção do portal: build do Vite + nginx servindo o estático.
# Multi-stage para não carregar Node no runtime. Escuta 8080 e é compatível
# com read-only (ver frontend/nginx.conf e infra/compose.yml).
FROM node:24-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
ENV VITE_OUT_DIR=dist
RUN npm run build

FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/nginx.conf
EXPOSE 8080
CMD ["nginx", "-g", "daemon off;"]