# Infraestrutura (rascunho)

Arquivos Docker de referência para o piloto, conforme [ADR-0004](../docs/06-arquitetura/decisoes/ADR-0004-conteineres-e-orquestracao.md), [ADR-0005](../docs/06-arquitetura/decisoes/ADR-0005-armazenamento-e-antivirus.md) e [ADR-0006](../docs/06-arquitetura/decisoes/ADR-0006-segredos-backups-e-observabilidade.md). Ainda não há código da aplicação, então as imagens `ccb-ebi-api` e `ccb-ebi-web` não existem.

| Arquivo | Para quê |
|---|---|
| `compose.yml` | Homologação e produção (uma VM por ambiente) |
| `compose.dev.yml` | Override para desenvolvimento local, com Mailpit |
| `.env.example` | Variáveis do Compose (tag da imagem, domínios, pasta de segredos) |
| `app.env.example` | Configuração não secreta do Laravel |

## Serviços

| Serviço | Imagem | Redes | Estado |
|---|---|---|---|
| proxy | Traefik | edge | certificados TLS |
| api | ccb-ebi-api (Octane/FrankenPHP) | edge, app, data | nenhum |
| worker | ccb-ebi-api (Horizon) | app, data | nenhum |
| scheduler | ccb-ebi-api (`schedule:work`) | app, data | nenhum |
| migrate | ccb-ebi-api (`migrate --force`, roda e termina) | app, data | nenhum |
| web | ccb-ebi-web (nginx, build estático) | edge | nenhum |
| postgres | postgres:17 | data | volume `pgdata` |
| redis | redis:7 | app | volume `redisdata` |
| minio | MinIO | data | volume `miniodata` |
| clamav | ClamAV | app | volume `clamdb` |

A rede `data` é interna: postgres e minio não têm saída para a internet e nenhum dos dois publica porta no host.

## Segredos

Um arquivo por segredo em `SECRETS_DIR` (por exemplo `/etc/ebi/secrets`, permissão `0400`): `app_key`, `db_password`, `redis_password`, `minio_root_user`, `minio_root_password`, `minio_app_secret`, `laudos_key`. Nunca versionar.

## Deploy

1. A CI publica `ghcr.io/helesjunior/ccb-ebi-api:<tag>` e `ccb-ebi-web:<tag>`.
2. Na VM: atualizar `APP_TAG` no `.env` e rodar `docker compose pull && docker compose up -d`. O `migrate` roda antes de a api subir.
3. A mesma tag testada em homologação é a que vai para produção.

## Pendências

- Destino dos backups fora da VM de produção (ADR-0006).
- Serviço de backup do PostgreSQL com WAL-G.
- Compose da observabilidade (Alloy, Loki, Prometheus, Grafana, Alertmanager).
- Dimensionamento das VMs depois do levantamento de volumetria (RNF-04).
