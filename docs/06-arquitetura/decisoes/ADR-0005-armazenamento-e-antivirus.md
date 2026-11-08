# ADR-0005 — Armazenamento de PDFs e verificação antivírus

**Status:** proposto · **Data:** 08/11/2026 · **Relaciona:** D-11, RF-10, RNF-01, ADR-0004

## Contexto
A biblioteca de atividades recebe PDFs enviados pelas equipes (RF-10). Upload malicioso é uma ameaça listada no RNF-01. A CCB não oferece storage S3 no piloto, e os arquivos precisam ser privados.

## Opções consideradas
1. **Disco local da VM (volume Docker).** Simples, mas amarra os arquivos ao host, dificulta replicação e obriga a servir downloads pela aplicação.
2. **MinIO em contêiner, compatível com S3.** A aplicação usa a API S3 (driver nativo do Laravel), com URLs assinadas e buckets separados. Trocar por um S3 da CCB depois é só configuração.
3. **S3 de nuvem pública.** Resolve durabilidade, mas tira os dados da infraestrutura da CCB, o que contraria RNF-06 sem aprovação.

## Decisão
Proposta: **opção 2**.

- Dois buckets privados: `quarentena` e `biblioteca`. Ninguém baixa nada da quarentena.
- Fluxo de upload:
  1. A api grava o arquivo em `quarentena` e cria um job.
  2. O worker valida o tipo real do arquivo (assinatura de PDF, não só a extensão), rejeita PDFs com JavaScript ou anexos embutidos e envia o conteúdo ao `clamd` pela porta 3310.
  3. Arquivo limpo vai para `biblioteca`; arquivo infectado ou inválido é apagado e o evento vai para a auditoria (RF-16).
- Downloads sempre por URL assinada de curta duração (minutos), gerada após checagem de autorização.
- O ClamAV atualiza as assinaturas sozinho (freshclam). O healthcheck da aplicação alerta se as assinaturas estiverem desatualizadas há mais de 48 h.
- Os laudos (dados sensíveis) ficam em bucket próprio, criptografados pela aplicação com chave separada antes do upload ([ADR-0006](ADR-0006-segredos-backups-e-observabilidade.md)).

## Consequências
- O ClamAV consome de 2 a 3 GB de RAM; a VM de produção precisa ser dimensionada para isso.
- Um PDF só fica disponível depois da verificação, o que leva segundos; a interface mostra o estado "em verificação".
- Os PDFs entram no plano de backup fora do host ([ADR-0006](ADR-0006-segredos-backups-e-observabilidade.md)).
