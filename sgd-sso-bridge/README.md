# sgd-sso-bridge

Puente OIDC entre el SGD legacy y el **SSO de la Municipalidad Distrital de San Jerónimo**.

Esta es la **Etapa 1**: comprobar el recorrido completo
`login → callback → token → validación → identidad obtenida`, **sin** crear
todavía la sesión Java del SGD. El resultado es una página que muestra `sub`,
`dni`, `name`, `iss`, `aud` y `exp` después de validar el ID token.

El Bridge corre en Node.js y se comunica con el SSO por OIDC Authorization Code
+ PKCE S256. No modifica el SSO ni el SGD.

## Requisitos

- Node.js ≥ 20 (probado con v22).
- El SSO corriendo y alcanzable (por defecto `http://127.0.0.1:8085`).
- Un usuario de prueba con DNI en el SSO, con su contraseña temporal ya cambiada.

## Puesta en marcha

1. **Instalar dependencias**

   ```powershell
   cd sgd-sso-bridge
   npm install
   ```

2. **Registrar el cliente en el SSO** (`http://127.0.0.1:8085/admin/clients`):

   - Redirect URI (coincidencia exacta): `http://127.0.0.1:8095/bridge/callback`
   - Cliente web confidencial, activo.
   - Copia el **client_secret** que se muestra **una sola vez**.

3. **Configurar el `.env`**

   ```powershell
   Copy-Item .env.example .env
   ```

   Completa en `.env`:

   - `SSO_CLIENT_ID` y `SSO_CLIENT_SECRET` del cliente recién registrado.
   - `SESSION_SECRET` con un valor aleatorio:

     ```powershell
     node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
     ```

   `SSO_ISSUER`, `PORT`, `BRIDGE_REDIRECT_URI` y `SSO_SCOPES` ya traen los
   valores por defecto de la prueba local.

4. **Arrancar el Bridge**

   ```powershell
   npm start
   ```

5. **Probar** — abre `http://127.0.0.1:8095/bridge/login`, inicia sesión en el
   SSO con el usuario de prueba y observa la página de identidad validada.

## Seguridad implementada en esta etapa

- `state`, `nonce` y `code_verifier` (PKCE S256) viven en la **sesión
  server-side** del Bridge; nunca en el navegador ni en query params.
- El canje `/token` es server-to-server con HTTP Basic; el `client_secret`
  nunca llega al navegador.
- El ID token se valida con firma RS256 vía JWKS del SSO, e `iss`, `aud`
  (= client_id), `exp`, `nonce` y `at_hash`.
- El DNI se toma **solo del ID token validado**, nunca de la query.
- En producción, `cookie.secure` debe ser `true` y el `redirect_uri` HTTPS; no
  desactivar validaciones TLS.

## Fuera de alcance (etapas siguientes)

- Resolución `DNI SSO → COD_USER` del SGD.
- Validaciones internas del SGD (usuario activo, empleado/dependencia, acceso
  al aplicativo 9, parámetros, permisos, UsuarioConfig).
- Creación de la `HttpSession` del SGD y entrada a `/sgd/1.0.0/mainpanel.do`.
