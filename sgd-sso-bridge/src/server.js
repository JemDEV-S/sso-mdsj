import crypto from 'node:crypto';
import express from 'express';
import session from 'express-session';
import jwt from 'jsonwebtoken';
import * as client from 'openid-client';
import { config } from './config.js';
import { getOidcConfig } from './oidc.js';

const app = express();
app.set('trust proxy', 1);

app.use(
  session({
    name: 'sgd_bridge_sid',
    secret: config.sessionSecret,
    resave: false,
    saveUninitialized: false,
    cookie: {
      httpOnly: true,
      sameSite: 'lax',
      // En producción (HTTPS) debe ser true. En la prueba local sobre
      // http://127.0.0.1 se mantiene false; nunca desactivar TLS en producción.
      secure: false,
    },
  }),
);

/** Escapa texto para interpolarlo con seguridad en el HTML de la página de resultado. */
function esc(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

app.get('/bridge/health', (_req, res) => {
  res.json({ status: 'ok', issuer: config.issuer, clientId: config.clientId });
});

/**
 * Inicio del flujo. Genera state, nonce y PKCE (code_verifier/code_challenge
 * S256), los guarda EN LA SESIÓN SERVER-SIDE del Bridge (nunca en el navegador
 * ni en query params) y redirige el navegador al /authorize del SSO.
 */
app.get('/bridge/login', async (req, res, next) => {
  try {
    const oidc = await getOidcConfig();

    const codeVerifier = client.randomPKCECodeVerifier();
    const codeChallenge = await client.calculatePKCECodeChallenge(codeVerifier);
    const state = client.randomState();
    const nonce = client.randomNonce();

    req.session.oidc = { codeVerifier, state, nonce };

    const authUrl = client.buildAuthorizationUrl(oidc, {
      redirect_uri: config.redirectUri,
      scope: config.scopes,
      state,
      nonce,
      code_challenge: codeChallenge,
      code_challenge_method: 'S256',
    });

    res.redirect(authUrl.href);
  } catch (err) {
    next(err);
  }
});

/**
 * Callback. Valida state contra la sesión, canjea el code por tokens
 * (server-to-server, HTTP Basic) y valida el ID token: firma por JWKS/kid,
 * RS256, iss, aud=client_id, exp, nonce y at_hash — todo dentro de
 * authorizationCodeGrant(). Luego MUESTRA los claims. No crea sesión del SGD.
 */
app.get('/bridge/callback', async (req, res, next) => {
  try {
    const stored = req.session.oidc;
    if (!stored) {
      return res.status(400).send('No hay un flujo OIDC en curso. Inicia en /bridge/login.');
    }
    // Consumir el estado una sola vez: evita reutilizar el mismo intento.
    delete req.session.oidc;

    const oidc = await getOidcConfig();

    const currentUrl = new URL(`${req.protocol}://${req.get('host')}${req.originalUrl}`);
    const tokens = await client.authorizationCodeGrant(oidc, currentUrl, {
      pkceCodeVerifier: stored.codeVerifier,
      expectedState: stored.state,
      expectedNonce: stored.nonce,
      idTokenExpected: true,
    });

    const claims = tokens.claims(); // claims del ID token ya validado

    // --- Etapa 2 · Paso 2: traspaso seguro al SGD ---
    // Si el SGD está configurado, emitir una credencial JWT de corta duración y
    // un solo uso, y redirigir el navegador al callback del SGD. El DNI viaja
    // FIRMADO (en el JWT), nunca como parámetro en claro.
    if (config.sgdSharedSecret && config.sgdCallbackUrl) {
      const dni = claims.dni;
      if (!dni) {
        // Sin DNI no se puede resolver el usuario del SGD.
        return res
          .status(400)
          .send('El ID token no incluye el claim "dni". Revisa el scope "dni" en el SSO.');
      }

      const now = Math.floor(Date.now() / 1000);
      const handoff = jwt.sign(
        {
          iss: config.sgdTokenIssuer,
          aud: config.sgdTokenAudience,
          sub: claims.sub,
          dni: String(dni),
          iat: now,
          exp: now + config.sgdTokenTtl,
          jti: crypto.randomUUID(),
        },
        config.sgdSharedSecret,
        { algorithm: 'HS256' },
      );

      const target = new URL(config.sgdCallbackUrl);
      target.searchParams.set('t', handoff);
      return res.redirect(target.href);
    }

    // --- Etapa 1 (sin SGD configurado): mostrar los claims para depuración ---
    const expDate = claims.exp ? new Date(claims.exp * 1000).toISOString() : '(sin exp)';

    res.set('Content-Type', 'text/html; charset=utf-8');
    res.send(`<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Identidad validada · SGD SSO Bridge</title>
<style>
  body{font-family:system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem;color:#1a1a1a}
  h1{font-size:1.3rem} .ok{color:#15803d} table{border-collapse:collapse;width:100%;margin-top:1rem}
  th,td{text-align:left;padding:.5rem .75rem;border-bottom:1px solid #e5e5e5;vertical-align:top}
  th{width:12rem;color:#555;font-weight:600} code{background:#f4f4f5;padding:.1rem .3rem;border-radius:3px}
  ul{line-height:1.8}
</style></head>
<body>
  <h1 class="ok">✓ Identidad obtenida y token validado</h1>
  <p>El ID token fue verificado por el Bridge: firma RS256 vía JWKS, e <code>iss</code>, <code>aud</code>, <code>exp</code> y <code>nonce</code> comprobados.</p>
  <table>
    <tr><th>sub</th><td><code>${esc(claims.sub)}</code></td></tr>
    <tr><th>dni</th><td><code>${esc(claims.dni ?? '(no incluido — ¿scope dni?)')}</code></td></tr>
    <tr><th>nombre (name)</th><td>${esc(claims.name ?? '(no incluido — ¿scope profile?)')}</td></tr>
    <tr><th>issuer (iss)</th><td><code>${esc(claims.iss)}</code></td></tr>
    <tr><th>audience (aud)</th><td><code>${esc(claims.aud)}</code></td></tr>
    <tr><th>expiración (exp)</th><td><code>${esc(claims.exp)}</code> → ${esc(expDate)}</td></tr>
  </table>
  <h2 style="font-size:1rem;margin-top:1.5rem">Validaciones aplicadas</h2>
  <ul>
    <li>Firma verificada contra el JWKS del SSO (algoritmo fijado a RS256).</li>
    <li><code>iss</code> coincide con el issuer esperado.</li>
    <li><code>aud</code> coincide con el client_id del Bridge.</li>
    <li><code>exp</code> en el futuro (token no expirado).</li>
    <li><code>nonce</code> coincide con el guardado en la sesión del Bridge.</li>
    <li><code>state</code> coincidió con el de la sesión (CSRF del flujo).</li>
  </ul>
  <p style="color:#777">Etapa 1: aquí termina. La creación de la sesión Java del SGD corresponde a una etapa posterior.</p>
</body></html>`);
  } catch (err) {
    next(err);
  }
});

app.get('/', (_req, res) => res.redirect('/bridge/login'));

// eslint-disable-next-line no-unused-vars
app.use((err, _req, res, _next) => {
  console.error('[bridge] Error en el flujo OIDC:', err);
  res.status(500).set('Content-Type', 'text/html; charset=utf-8').send(
    `<!doctype html><meta charset="utf-8"><h1>Error en el flujo OIDC</h1>` +
      `<p>No se pudo completar la autenticación. Revisa la consola del Bridge.</p>` +
      `<pre style="background:#f4f4f5;padding:1rem;white-space:pre-wrap">${esc(err?.message ?? err)}</pre>` +
      `<p><a href="/bridge/login">Reintentar</a></p>`,
  );
});

// Host de escucha: 127.0.0.1 por defecto (solo local). Para exponer el Bridge
// en la red local durante pruebas, poner BIND_HOST=0.0.0.0 en el .env.
const bindHost = (process.env.BIND_HOST || '127.0.0.1').trim();
app.listen(config.port, bindHost, () => {
  console.log(`[bridge] Escuchando en http://${bindHost}:${config.port}`);
  console.log(`[bridge] Inicia la prueba en  ${config.redirectUri.replace('/bridge/callback', '/bridge/login')}`);
  console.log(`[bridge] Issuer SSO: ${config.issuer}`);
});
