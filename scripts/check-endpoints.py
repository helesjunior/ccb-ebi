#!/usr/bin/env python3
"""Confere se docs/04-api/endpoints.md está alinhado com docs/04-api/openapi.yaml.

Regras:
- Toda operação (método + caminho) do OpenAPI precisa ter uma linha marcada
  `no OpenAPI` em endpoints.md.
- Toda linha marcada `no OpenAPI` precisa existir no OpenAPI.
Nomes de parâmetros são ignorados na comparação ({id} == {sessionId}).
Sai com código 1 se houver divergência.

Premissas de layout do catálogo (endpoints.md), das quais a leitura depende:
- Uma linha por operação, começando com `|` (linha de tabela Markdown).
- A string exata `no OpenAPI` marca a linha; sem ela, a linha é ignorada.
- Coluna 1 = método(s), separados por `/` (ex.: `GET/POST/PATCH`); cada método
  vale para todos os caminhos da linha.
- Coluna 2 = finalidade, com o(s) caminho(s) entre crases; vários caminhos na
  mesma linha geram o produto cartesiano método × caminho.
Se o formato da tabela mudar, ajuste o parser de `operacoes_marcadas`.
"""
import re
import sys
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parent.parent
OPENAPI = ROOT / "docs/04-api/openapi.yaml"
CATALOGO = ROOT / "docs/04-api/endpoints.md"
METODOS = {"get", "post", "put", "patch", "delete"}


def normalizar(caminho: str) -> str:
    return re.sub(r"\{[^}]+\}", "{}", caminho.strip())


def operacoes_openapi() -> set:
    spec = yaml.safe_load(OPENAPI.read_text(encoding="utf-8"))
    ops = set()
    for caminho, item in (spec.get("paths") or {}).items():
        for metodo in item:
            if metodo.lower() in METODOS:
                ops.add((metodo.upper(), normalizar(caminho)))
    return ops


def operacoes_marcadas() -> set:
    ops = set()
    for linha in CATALOGO.read_text(encoding="utf-8").splitlines():
        if not linha.startswith("|") or "no OpenAPI" not in linha:
            continue
        colunas = [c.strip() for c in linha.strip("|").split("|")]
        metodos = colunas[0].split("/")
        caminhos = re.findall(r"`(/[^`]+)`", colunas[1])
        for metodo in metodos:
            for caminho in caminhos:
                ops.add((metodo.strip().upper(), normalizar(caminho)))
    return ops


def main() -> int:
    contrato, catalogo = operacoes_openapi(), operacoes_marcadas()
    faltam_no_catalogo = sorted(contrato - catalogo)
    faltam_no_contrato = sorted(catalogo - contrato)
    for metodo, caminho in faltam_no_catalogo:
        print(f"No OpenAPI, mas sem marca `no OpenAPI` em endpoints.md: {metodo} {caminho}")
    for metodo, caminho in faltam_no_contrato:
        print(f"Marcado `no OpenAPI` em endpoints.md, mas ausente do OpenAPI: {metodo} {caminho}")
    if faltam_no_catalogo or faltam_no_contrato:
        return 1
    print(f"OK: {len(contrato)} operações alinhadas.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
