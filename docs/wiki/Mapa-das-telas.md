# Mapa das telas

O menu tem quatro grupos. A tabela mostra **quem enxerga cada tela**; quem não tem a função não
vê o item — e, mesmo com o endereço da tela, o servidor recusa a ação.

| Grupo | Tela | Para que serve | Quem enxerga |
|---|---|---|---|
| Geral | **Início** | Visão geral e atalhos | Todos |
| Participantes | **Meus dependentes** | Crianças e adolescentes que você representa | Todos (só as ligadas a você) |
| Participantes | **Sessões** | Abrir, encerrar e registrar presença | Super Admin, Admin regional, Admin de setor, Admin de igreja, Coordenação, Auxiliar, Ministério local |
| Participantes | **Escalas** | Montar a equipe de cada sessão e acompanhar cobertura | Super Admin, Admin regional, Admin de setor, Admin de igreja, Coordenação, Ministério local |
| Participantes | **Minhas escalas** | Confirmar presença, pedir troca, ver conflitos | Coordenação, Auxiliar |
| Participantes | **Equipes de escala** | Montar a equipe de auxiliares de cada coordenação | Admin de igreja, Coordenação |
| Participantes | **Participantes** | Buscar e cadastrar participantes | Super Admin, Admin regional, Admin de setor, Admin de igreja, Coordenação, Auxiliar, Ministério local |
| Participantes | **Cadastro assistido** | Cadastro presencial com evidência de aceite | Super Admin, Admin de igreja, Coordenação, Auxiliar, Responsável |
| Participantes | **Exceção inclusiva** | Pedir ou decidir exceção de faixa etária | Os mesmos de **Participantes** |
| Participantes | **Mesclagem** | Juntar cadastros duplicados (reversível) | Super Admin, Coordenação |
| Administração | **Equipe** | Usuários do escopo e convites | Super Admin, Admin regional, Admin de setor, Admin de igreja |
| Administração | **Vínculos** | Pedidos de vínculo entre responsável e participante | Todos (o responsável vê os próprios pedidos) |
| Administração | **Auditoria** | Trilha de eventos do sistema | Super Admin, Admin regional, Admin de setor |
| Administração | **Relatórios** | Totais de presença e participantes por unidade | Administradores, Ministérios, Coordenação |
| Minha conta | **Meu perfil** | Nome, e-mail, senha e verificação em duas etapas | Todos |
| Minha conta | **Dispositivos** | Onde a conta está conectada | Todos |
| Minha conta | **Privacidade** | Termo, consentimento e pedidos sobre seus dados | Todos |

"Coordenação" cobre **Coordenadora EBI** e **Coordenadora chefe**. A diferença entre as duas está
em [Escalas](Escalas): a chefe aprova a escala e decide as trocas.

## As telas, uma a uma

### Início

Cartões de atalho, na ordem em que as pessoas costumam trabalhar. O rodapé da lateral mostra a
conta e o contexto de atuação.

![Início](images/02-inicio-chefe.png)

### Sessões

Escolha o EBI e o período. A tabela lista cada encontro com data, tema, situação
(prevista/aberta/encerrada) e presença; abaixo ficam as **faixas** de horário da agenda e as
**exceções** de calendário.

![Sessões](images/03-sessoes-chefe.png)

Detalhes em [Sessões e agenda](Sessoes-e-agenda).

### Escalas

Para cada sessão do período: a situação da escala (**Sem escala**, **Abaixo do mínimo**,
**Completa**, **Aprovada**) e quantos auxiliares estão escalados e confirmados.

![Escalas](images/04-escalas-chefe.png)

Ao abrir **Detalhe**, aparecem a equipe mínima, a estimativa de participantes, a lista de pessoas,
as vagas abertas e as trocas pendentes.

![Detalhe da escala](images/04b-escalas-chefe-detalhe.png)

Detalhes em [Escalas](Escalas).

### Minhas escalas

A própria agenda. Cada linha traz data, função e situação (**Aguardando aprovação**, **Escalado**,
**Confirmado**), com os botões para confirmar presença, avisar conflito e pedir troca.

![Minhas escalas do auxiliar](images/12-minhas-escalas-auxiliar.png)

Detalhes em [Fluxo do auxiliar](Fluxo-do-auxiliar).

### Equipes de escala

Cada coordenação tem uma equipe de auxiliares. A escala de uma sessão nasce de uma dessas equipes.

![Equipes de escala](images/05-equipes-chefe.png)

### Participantes

Busca por nome (com a finalidade da leitura registrada) e formulário de cadastro.

![Participantes](images/07-participantes-chefe.png)

Detalhes em [Participantes](Participantes).

### Cadastro assistido

Para cadastrar uma criança presencialmente, anexando a foto ou o PDF do termo assinado pelo
responsável.

![Cadastro assistido](images/21-cadastro-assistido.png)

### Exceção inclusiva

Pedido de exceção (responsável) e decisão (coordenação) no mesmo lugar.

![Exceção inclusiva](images/22-excecao.png)

Detalhes em [Exceção inclusiva](Excecao-inclusiva).

### Mesclagem

Busca de possíveis duplicados, escolha de qual cadastro fica e fusão reversível.

![Mesclagem](images/24-mesclagem.png)

### Equipe

Usuários com acesso no escopo e formulário de convite por e-mail.

![Equipe](images/16-equipe-igreja.png)

### Vínculos

Pedidos de vínculo entre responsável e participante, com aprovação, recusa e pedido de documento.

![Vínculos](images/23-vinculos.png)

### Auditoria

Trilha de eventos do sistema, filtrável por recurso, ator e período. Cada evento tem número de
sequência, data, ação, recurso e origem.

![Auditoria](images/18-auditoria-super.png)

### Relatórios

Totais agregados por unidade, com exportação em CSV.

![Relatórios](images/25-relatorios-chefe.png)

Detalhes em [Relatórios](Relatorios).

### Meu perfil, Dispositivos e Privacidade

![Meu perfil](images/08-perfil-chefe.png)

![Dispositivos](images/27-dispositivos.png)

![Privacidade](images/26-privacidade.png)

Detalhes em [Minha conta](Minha-conta).
