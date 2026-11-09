// Loader de resolução para o codegen da API (npm run gen:api).
//
// Por que existe: o projeto usa typescript@7, mas openapi-typescript@7.13.0
// depende da API de compilador (ts.factory.*) que o TypeScript 7 removeu — só
// funciona com TypeScript 5. Isolamos o TS 5 na dependência apelidada
// "typescript5" e, aqui, remapeamos o specifier 'typescript' para ela apenas
// durante o codegen. O build/lint continuam usando o TypeScript 7 normalmente.
export async function resolve(specifier, context, nextResolve) {
  if (specifier === 'typescript') {
    return nextResolve('typescript5', context);
  }
  return nextResolve(specifier, context);
}