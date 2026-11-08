# ADR-0004 — Contêineres Docker e orquestração com Compose no piloto

**Status:** proposto · **Data:** 08/11/2026 · **Relaciona:** D-11, ADR-0002, RNF-03, RNF-06

## Contexto
Todo o sistema vai rodar em contêineres Docker. No piloto da Regional de Brasília, a TI da CCB não oferece Kubernetes, registry de imagens nem storage compatível com S3. O backend é Laravel (confirmado). A meta de disponibilidade do piloto é 99,5% ao mês, com RPO de 24 h e RTO de 8 h (RNF-03), o que tolera janelas curtas de manutenção.

## Opções consideradas
1. **Docker Compose em uma VM por ambiente.** Operação simples, um arquivo descreve tudo, fácil de entender para uma equipe pequena. Sem alta disponibilidade: se a VM cair, o sistema cai até ela voltar. Deploy tem alguns segundos de indisponibilidade.
2. **Docker Swarm.** Vários nós e secrets nativos com o mesmo formato do Compose. Ecossistema parado, pouca gente operando, risco de ficar sem suporte.
3. **Kubernetes (ou k3s).** Alta disponibilidade, escala horizontal e padrão de mercado. Custo de operação alto demais para um piloto sem cluster existente na CCB.

## Decisão
Proposta: **opção 1** no piloto.

- **Uma imagem da aplicação** Laravel (FrankenPHP/Octane) usada em quatro papéis, mudando só o comando: `api`, `worker` (Horizon), `scheduler` (`schedule:work`) e `migrate` (job único a cada deploy).
- **Uma imagem do web** (portal administrativo e portal dos responsáveis, build estático servido por nginx).
- Serviços de apoio em contêiner: proxy reverso (Traefik), PostgreSQL, Redis, MinIO e ClamAV. Detalhes em [ADR-0005](ADR-0005-armazenamento-e-antivirus.md).
- **Homologação e produção em VMs separadas**, com segredos, buckets e bancos diferentes (RNF-06).
- **Registry:** GitHub Container Registry (GHCR) privado, ligado ao repositório. A CI (GitHub Actions) faz o build uma vez; a mesma imagem, identificada pelo digest, é promovida de homologação para produção.
- **Redes:** `edge` (proxy, api, web), `app` (api, worker, scheduler, redis, clamav) e `data` (postgres, minio), esta última `internal`, sem saída para a internet. Só o proxy publica portas no host (80 e 443).
- Contêineres da aplicação não guardam estado; só postgres, minio, redis e clamav têm volumes.
- Desenvolvimento local com um arquivo de override que troca o e-mail real por Mailpit.

O rascunho está em [`infra/compose.yml`](../../../infra/compose.yml).

## Consequências
- Falha da VM de produção derruba o sistema; o plano de contingência de presença manual (RNF-03) cobre esse cenário.
- A aplicação deve ser *twelve-factor*: configuração por variável de ambiente ou arquivo de secret, logs em stdout, nada gravado em disco local. Isso mantém aberta a migração para Kubernetes na fase nacional sem reescrever a aplicação.
- A troca para Kubernetes vira um novo ADR quando a escala ou a exigência de alta disponibilidade justificar, ou se a CCB passar a oferecer um cluster.
- Aguarda validação da TI da CCB (D-11).
