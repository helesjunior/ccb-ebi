import createClient from 'openapi-fetch'

import type { paths } from './schema'

/**
 * Cliente HTTP tipado, gerado a partir do OpenAPI (docs/04-api/openapi.yaml).
 * A URL base é configurável por VITE_API_URL; o padrão é a API local.
 */
export const api = createClient<paths>({
  baseUrl: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1',
})