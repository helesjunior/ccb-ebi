# ADR-0006 — Segredos, backups e observabilidade

**Status:** proposto · **Data:** 08/11/2026 · **Relaciona:** D-11, RNF-01, RNF-03, RNF-07, ADR-0004

## Contexto
Com tudo em contêineres numa VM por ambiente ([ADR-0004](ADR-0004-conteineres-e-orquestracao.md)), é preciso definir onde ficam senhas e chaves, como os dados sobrevivem à perda da VM e como se descobre que algo quebrou. As metas são RPO de 24 h e RTO de 8 h (RNF-03).

## Decisão

### Segredos
- Nenhum segredo no repositório nem em `.env` versionado.
- Em homologação e produção, segredos como arquivos montados pelo Compose (`secrets:`), lidos pela aplicação via variáveis `*_FILE`. Os arquivos ficam na VM com permissão `0400`, fora do diretório do projeto.
- A chave de criptografia dos laudos é separada da `APP_KEY` e de todas as outras credenciais, e só o módulo de dados sensíveis a lê (ADR-0002).
- Credenciais diferentes por ambiente. Rotação documentada no runbook.
- Se a CCB adotar um cofre (por exemplo, Vault), ele substitui os arquivos sem mudar a aplicação.

### Backups
- **PostgreSQL:** WAL-G com backup completo diário e arquivamento contínuo de WAL. Na prática o RPO fica em minutos, bem abaixo das 24 h.
- **MinIO:** replicação dos buckets `biblioteca` e de laudos para outro destino.
- **Destino fora da VM de produção (obrigatório).** Sem storage S3 da CCB, as opções são um MinIO na VM de homologação, um storage externo aprovado pela CCB ou um disco em outra máquina. **A escolha está pendente** e deve ser feita antes do piloto.
- Backups criptografados antes de sair da VM. Retenção conforme RNF-07.
- **Restauração de teste mensal** em homologação, com o tempo medido e registrado para comprovar o RTO.

### Observabilidade
- Logs em JSON no stdout de todos os contêineres, sem senhas, tokens, códigos MFA ou dados de participantes (RNF-01).
- Stack em compose separado: Grafana Alloy coleta logs e métricas, Loki armazena logs, Prometheus armazena métricas, Grafana exibe, Alertmanager envia alertas.
- Erros da aplicação no Sentry (pode ser self-hosted ou a versão em nuvem, se a CCB aprovar).
- Healthcheck em todo serviço; alertas mínimos: serviço fora do ar, fila parada, disco acima de 80%, falha de backup, assinaturas do antivírus desatualizadas, certificado TLS perto de vencer.
- Monitor externo de disponibilidade (fora da VM) para medir os 99,5%.

## Consequências
- A stack de observabilidade consome memória e disco na mesma VM no piloto; se pesar, vai para uma VM própria.
- A trilha de auditoria (RF-16) fica no PostgreSQL, não nos logs técnicos, e segue a retenção própria do RNF-07.
- Falta decidir o destino dos backups fora da VM.
