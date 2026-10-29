import 'dotenv/config';

/**
 * Carga y valida la configuración del Bridge desde variables de entorno.
 * Falla rápido (al arrancar) si falta algo esencial, para no descubrir el
 * problema a mitad del flujo OIDC.
 */
function required(name) {
  const value = process.env[name];
  if (!value || value.trim() === '') {
    throw new Error(`Falta la variable de entorno obligatoria: ${name}. Revisa tu .env (ver .env.example).`);
  }
  return value.trim();
}

const issuer = required('SSO_ISSUER').replace(/\/+$/, '');
const redirectUri = required('BRIDGE_REDIRECT_URI');
const scopes = (process.env.SSO_SCOPES || 'openid profile dni').trim();

if (!scopes.split(/\s+/).includes('openid')) {
  throw new Error("SSO_SCOPES debe incluir 'openid'.");
}

// --- Traspaso seguro al SGD (Etapa 2 · Paso 2) ---
// Opcionales al arrancar (la Etapa 1 no los necesita), pero /bridge/callback
// falla con un mensaje claro si se usan sin estar configurados. El secreto debe
// ser EXACTAMENTE el mismo que SGD_SSO_SHARED_SECRET en el servidor del SGD.
const sgdSharedSecret = (process.env.SGD_SHARED_SECRET || '').trim();
const sgdCallbackUrl = (process.env.SGD_CALLBACK_URL || '').trim();
const sgdTokenTtl = Number(process.env.SGD_TOKEN_TTL_SECONDS || 60);

export const config = {
  issuer,
  clientId: required('SSO_CLIENT_ID'),
  clientSecret: required('SSO_CLIENT_SECRET'),
  redirectUri,
  scopes,
  port: Number(process.env.PORT || 8095),
  sessionSecret: required('SESSION_SECRET'),
  // Traspaso al SGD: emisor y audiencia deben coincidir con EXPECTED_ISS /
  // EXPECTED_AUD del SsoBridgeController en el SGD.
  sgdSharedSecret,
  sgdCallbackUrl,
  sgdTokenTtl: Number.isFinite(sgdTokenTtl) && sgdTokenTtl > 0 ? sgdTokenTtl : 60,
  sgdTokenIssuer: 'sgd-sso-bridge',
  sgdTokenAudience: 'sgd',
};
