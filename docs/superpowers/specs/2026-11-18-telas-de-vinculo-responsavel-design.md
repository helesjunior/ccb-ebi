# Design — Telas de vínculo do responsável (RF-03 / FL-01)

**Data:** 18/11/2026 · **Base:** `main` @ `0bf4402` · **Épico:** E03 (RF-02, RF-03) · **Escopo:** fechar o fluxo FL-01 no app

## Intenção e por quê

O fluxo de vínculo existe no backend e numa tela, mas é inutilizável: `Vinculos.tsx`
pede o ULID do participante e o ULID do vínculo, valores que o produto nunca mostra.
E não existe listagem de pedidos — a coordenadora aprova "no escuro". Resultado
prático: um participante cadastrado nunca chega em "meus dependentes", porque o
vínculo nunca é legitimado (CA-04 / RN-05).

## Objetivos

- A coordenadora vê os pedidos de vínculo do seu território e decide por botão.
- O responsável solicita vínculo escolhendo o participante por nome (nunca por ID),
  e vê o estado dos pedidos que ele mesmo abriu.
- Fechar o furo de escopo: decidir vínculo de participante fora do território é negado.

## Não-objetivos

- Notificação (RF-15) de pedido/decidido.
- Upload de certidão/documento (ADR-0018) — `pedir_documento` só registra a decisão.
- Paginação/cursor na listagem: volume baixo, lista limitada no backend.
- Conta própria de responsável com MFA: `responsavel` já é isento (GerenciadorMfa).

## Restrições

- Fronteira modular (ADR-0002): controllers não tocam modelos de Participantes.
- Autorização territorial é do `AutorizacaoParticipante`; a de responsável é por
  propriedade (`VerificadorPropriedadeParticipante`), nunca por território.
- Contract-first: `docs/04-api/openapi.yaml` (CRLF) antes do código; `schema.d.ts` gerado.
- Respostas de coleção neste produto são divergentes; a operação nova nasce no
  formato correto `{data: [...]}`.

## Abordagens consideradas

- **A — listagem no backend + tela em duas abas (escolhida).** `GET /guardian-requests`
  com recorte duplo (território para quem gerencia, propriedade para o responsável),
  recurso do vínculo enriquecido com o participante, tela com *Pedidos* e *Solicitar*.
- **B — só front, apoiado em `GET /participants`.** Barata, mas não resolve a decisão:
  sem listagem, aprovar volta a exigir colar ULID.
- **C — listagem com filtros e paginação completos.** Sem uso no volume atual;
  complexidade sem retorno.

## Decisões

1. **Recorte da listagem (dupla face).** Quem tem `vinculo.gerenciar` vê os vínculos
   cujo participante está sob seu território. Quem não tem, vê os vínculos em que
   `usuario_id` é o próprio ator (somente leitura na tela). Os dois conjuntos são
   unidos e deduplicados. Vínculos com `usuario_id` nulo (cadastro assistido) só
   aparecem pelo recorte territorial — não há dono para o recorte de propriedade.
2. **Sem vazamento entre recortes.** O recorte por território é filtrado pelo mesmo
   `AutorizacaoParticipante::pode(..., 'vinculo.gerenciar')` usado no `decidir`; o
   de propriedade exige `usuario_id` igual ao ator.
3. **Escopo no `decidir`.** Passa a exigir que o participante do vínculo esteja sob o
   território do ator com `vinculo.gerenciar`. Vínculo fora do território → `404`
   (não revela existência). Vínculo inexistente → `404`.
   O responsável **não decide**: contestar/revogar fica com a coordenadora nesta
   entrega; a rota segue sob `can:vinculo.gerenciar` (sem mudança de RBAC).
4. **Enriquecimento do recurso.** `ResponsavelParticipanteResource` **ganha um campo
   novo** `participante: {id, nome, matricula}` (não substitui `nome`/`participante_id`,
   que seguem existindo), para a tabela não mostrar ULID. A listagem carrega a
   relação com eager load; `ParticipanteResumo` vem do contrato de Participantes,
   não do model, na fronteira modular.
5. **Tela.** `Vinculos.tsx` vira duas abas: *Pedidos* (tabela com criança, responsável,
   relação, situação, data e a decisão como menu de ações) e *Solicitar* (busca por
   nome via `GET /participants?q=`, seleção, relação e telefone). Nenhum campo de ULID.
   A aba *Solicitar* é exibida apenas para papéis com `participante.ler`; para o
   responsável a tela mostra só *Pedidos* (os dele), em leitura. Não se abre
   `participante.ler_dependente` nesta entrega.
6. **Filtro de situação na UI.** Padrão `pendente`; alterna para `todos` numa caixa de seleção.
   O parâmetro de contrato é `validacao` (o campo que a máquina de estados usa:
   `pendente|aprovada|aguardando_documento|recusada|todos`), não `status` (sempre `ativo`).
   A listagem é auditada como leitura identificável: exige `finalidade` declarada,
   como `GET /participants`.

## Como fica por dentro

- Contrato: `GET /guardian-requests` (`validacao` opcional:
  `pendente|aprovada|aguardando_documento|recusada|todos`, default `pendente`;
  `finalidade` obrigatória) → `{data: [ResponsavelParticipante]}`.
  `ResponsavelParticipante` ganha o campo `participante` (schema novo
  `VinculoParticipanteResumo`).
- `GerirVinculoResponsavel::listar(string $atorId, ?ContextoAtuacao $contexto, string $validacao)`.
  O recorte territorial usa o contrato de autorização (`AutorizacaoParticipante`),
  não SQL de hierarquia.
- `decidir` valida território antes de mutar; fora do território `404`.

## Deixado ao construtor

- Nomes exatos de métodos/classes auxiliares e a forma de compor o filtro duplo.
- Limite da lista e ordenação (mais recentes primeiro).
- Reaproveitar ou extrair o componente de tabela de `Dependentes.tsx`.
- Textos e rótulos de situação (`rotuloStatus` já existe em `lib/formatos`, mas
  conferir `aguardando_documento`).
- Como checar `participante.ler` na UI para exibir a aba *Solicitar* (o `/me` já
  devolve os escopos; a matriz é espelhada em `ListaNav`).
