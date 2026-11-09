# Correções da revisão da Etapa 2

- [ ] B1: `ImportadorBrasilia` — transação única no loop inteiro + teste `Igreja::count() === 0` após falha
- [x] B2: idempotência antes da validação (middleware) + teste HTTP de replay de `POST /churches`
- [ ] I1: `ServicoTransferencia` — `inicio <= $inicio` + teste
- [ ] I2: índice único parcial `igreja_vinculacao (igreja_id) WHERE fim IS NULL`
- [x] I3: re-gravar `endpoints.md` sem mojibake
- [ ] M1: N+1 em `ChurchController::index`
- [ ] M3: `ProblemException` — mensagem em `detail`, `title` generico
- [ ] M4: `ImportadorBrasilia` valida cabeçalho do CSV
- [ ] Gates: pint, phpstan, test, spectral, check-endpoints
- [ ] Push + PR