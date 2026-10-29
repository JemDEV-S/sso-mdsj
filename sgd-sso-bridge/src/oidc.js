import * as client from 'openid-client';
import { config } from './config.js';

/**
 * Descubre la configuración del SSO vía /.well-known/openid-configuration y
 * construye la configuración de cliente que usará openid-client.
 *
 * openid-client v6 descarga y cachea el JWKS internamente a partir del
 * jwks_uri del discovery, y fija el algoritmo de firma esperado. La validación
 * de iss, aud, exp, nonce y at_hash la realiza la propia librería durante
 * authorizationCodeGrant().
 */
let cached = null;

export async function getOidcConfig() {
  if (cached) return cached;

  const issuerUrl = new URL(config.issuer);
  const insecure = issuerUrl.protocol === 'http:';

  // openid-client v6 prohíbe HTTP por defecto. Solo para la prueba local sobre
  // http://127.0.0.1 habilitamos el transporte inseguro de forma explícita, y
  // únicamente durante el propio discovery (que también se hace por HTTP).
  // En cuanto el issuer sea HTTPS (producción) esto NO se aplica: nunca se
  // relajan las validaciones TLS en producción.
  const discoveryOptions = insecure ? { execute: [client.allowInsecureRequests] } : undefined;

  // client_secret_basic: el canje /token autentica al cliente con HTTP Basic,
  // server-to-server. El secreto nunca llega al navegador.
  cached = await client.discovery(
    issuerUrl,
    config.clientId,
    config.clientSecret,
    client.ClientSecretBasic(config.clientSecret),
    discoveryOptions,
  );

  // Propaga el permiso HTTP a las operaciones posteriores (token, jwks) sobre
  // la misma Configuration. Solo en local; en HTTPS no se toca nada.
  if (insecure) {
    client.allowInsecureRequests(cached);
  }

  return cached;
}
