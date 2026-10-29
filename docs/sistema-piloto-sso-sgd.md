# Sistema Piloto SSO ↔ SGD — Documentación de implementación

> **Estado:** ✅ **FUNCIONAL Y PROBADO END-TO-END**, incluso desde otras máquinas de
> la red local (2026-10-07). Un usuario inicia sesión una sola vez en el SSO
> municipal y entra al SGD **ya logueado**, con su sesión, menú y configuración.
>
> Última actualización: 2026-10-07.

---

## 1. Resumen ejecutivo

Se integró el **SGD legacy** (sistema de gestión documental sobre
Payara/GlassFish, bytecode Java 6, Spring 3.2.6) como primer cliente del **SSO
municipal** (Laravel + PostgreSQL, OpenID Connect) **sin reescribir** la
autenticación ni la autorización internas del SGD.

El resultado: desde el portal del SSO, el usuario ve una tarjeta del SGD; al
hacer clic, el navegador recorre un flujo OIDC, y el SGD crea su propia
`HttpSession` reutilizando sus servicios existentes. **Single Sign-On real**: no
se vuelve a pedir usuario/contraseña.

### Lo que se logró

- Login único en el SSO → acceso al SGD sin segundo login.
- El SGD **no fue modificado** en su núcleo: se añadió un único controlador nuevo
  que convive con el resto por *component-scan*, sin tocar XML, filtros ni los
  controladores existentes.
- El DNI del usuario **nunca viaja en claro por la URL**: se transporta dentro de
  una credencial firmada (JWT HS256) de corta duración y un solo uso.
- Portal de sistemas en el SSO: pantalla "Tus sistemas" con tarjetas clicables.
- Todo probado en red local desde varias PCs.

---

## 2. Arquitectura y flujo

### Componentes

| Componente | Tecnología | Rol | Dónde corre (piloto) |
|---|---|---|---|
| **SSO** | Laravel + PostgreSQL | Proveedor de identidad OIDC (login, authorize, token, jwks). Portal de sistemas. | `http://192.168.50.223:8085` |
| **Bridge** | Node.js + Express + openid-client | Cliente OIDC del SSO. Valida el ID token y emite la credencial de traspaso al SGD. | `http://192.168.50.223:8095` |
| **SGD** | Java 6 / Spring 3.2.6 / Payara | Sistema destino. Nuevo `SsoBridgeController` que canjea la credencial por una `HttpSession`. | `http://192.168.90.12:8080/sgd` |

> **Por qué un Bridge intermedio:** el SGD es Java 6 / Spring 3.2.6; integrar un
> cliente OIDC moderno (PKCE, JWKS, rotación de claves) ahí sería frágil. El
> Bridge en Node aísla toda la complejidad OIDC y entrega al SGD algo que éste sí
> puede validar con sus librerías existentes: un JWT HMAC simple.

### Flujo completo (paso a paso)

```text
1. Usuario → http://192.168.50.223:8085/           (portal del SSO, ya logueado)
2. Clic en la tarjeta "SGD"  →  launch_url del cliente
3. Navegador → Bridge /bridge/login
      · genera state, nonce y PKCE (S256), los guarda en la sesión del Bridge
      · redirige a SSO /authorize
4. SSO /authorize  →  (como ya hay sesión en el SSO, no re-pregunta)
      · emite authorization code  →  redirige al Bridge /bridge/callback
5. Bridge /bridge/callback
      · canjea el code por tokens (server-to-server, HTTP Basic)
      · VALIDA el ID token: firma RS256 vía JWKS, iss, aud, exp, nonce, at_hash
      · firma un JWT HS256 corto { iss, aud, sub, dni, iat, exp:+60s, jti }
        con el SECRETO COMPARTIDO con el SGD
      · redirige el navegador a:
           http://192.168.90.12:8080/sgd/sso-callback?t=<jwt>
6. SGD /sso-callback  (SsoBridgeController)
      · valida el JWT: firma HS256, exp, iss, aud, alg, jti (anti-replay)
      · extrae el DNI
      · resuelve identidad local, valida acceso al aplicativo, carga permisos
        y configuración (reutilizando los servicios del SGD)
      · crea la HttpSession del SGD
      · FORWARD interno → /1.0.0/mainpanel.do  (panel con sesión válida)
```

### Mapeo de identidad

- `DNI (del SSO) → COD_USER (del SGD)`: en el piloto el `COD_USER` del SGD **es
  el DNI** tal cual.
- Aplicativo del SGD dentro de su modelo de seguridad: `COD_APLICA = "9"`.

---

## 3. Decisiones de diseño y seguridad

### 3.1 Credencial de traspaso: JWT HS256

Se eligió **JWT firmado con HMAC-SHA256** (clave simétrica compartida) frente a un
token opaco con verificación back-channel, por dos razones:

1. **Sin red extra entre servidores.** El SGD valida el JWT solo con la clave
   compartida; no necesita llamar de vuelta al Bridge. Como el Bridge y el SGD
   están en **servidores/subredes distintas**, esto evita tener que abrir
   conectividad SGD→Bridge. El único canal es el navegador del usuario, que ya
   alcanza a ambos.
2. **Simplicidad en el lado Java 6.** La validación HMAC-SHA256 se hace con
   `javax.crypto.Mac` (presente desde Java 1.4), sin añadir ninguna librería al
   SGD.

### 3.2 Propiedades de seguridad de la credencial

| Propiedad | Cómo se garantiza |
|---|---|
| **Integridad / autenticidad** | Firma HMAC-SHA256 con secreto compartido. Comparación en **tiempo constante** para no filtrar información por *timing*. |
| **Corta duración** | `exp = iat + 60s` (configurable). El SGD rechaza tokens expirados con una tolerancia de reloj de 30 s. |
| **Un solo uso (anti-replay)** | Claim `jti` (UUID). El SGD guarda los `jti` consumidos en memoria con su expiración; un `jti` repetido se rechaza (`ssoError=replay`). Se purgan al expirar. |
| **Destino correcto** | Claims `iss="sgd-sso-bridge"` y `aud="sgd"` verificados. |
| **Algoritmo fijado** | Se exige `alg="HS256"` explícitamente (evita el ataque `alg:none`). |
| **DNI no expuesto** | El DNI va **dentro** del JWT firmado, nunca como parámetro en claro manipulable. |

### 3.3 Validaciones OIDC (en el Bridge)

La librería `openid-client` v6 valida durante `authorizationCodeGrant()`: firma
**RS256** contra el **JWKS** del SSO, `iss`, `aud` (= client_id del Bridge),
`exp`, `nonce` y `at_hash`. Además, el Bridge usa **PKCE (S256)** y verifica el
`state` (CSRF del flujo). El canje del code es **server-to-server** con HTTP
Basic: el `client_secret` nunca llega al navegador.

### 3.4 Gestión del secreto compartido

- Es una **clave simétrica**: el mismo valor en el Bridge y en el SGD.
- **Bridge:** variable `SGD_SHARED_SECRET` en `sgd-sso-bridge/.env` (no versionado).
- **SGD:** se lee de `System.getenv/getProperty("SGD_SSO_SHARED_SECRET")`. En
  Payara se pasa como **opción de JVM** `-DSGD_SSO_SHARED_SECRET=...`.
- **Nunca** se hardcodea ni se versiona. Generación:
  `node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"`.

### 3.5 TLS / producción

- En el piloto todo va por HTTP sobre red local. El Bridge solo relaja el
  transporte HTTP cuando el issuer es `http://` (local); con HTTPS no se relaja
  nada.
- **En producción, todo debe ir por HTTPS** (issuer, redirect_uri, callback del
  SGD) y las cookies de sesión con `secure=true`.

---

## 4. Detalle de la implementación por componente

### 4.1 SGD — `SsoBridgeController.java`

Ubicación: `sgd-sso-bridge-java/src/pe/gob/onpe/tramitedoc/web/controller/SsoBridgeController.java`
Paquete `pe.gob.onpe.tramitedoc.web.controller` → lo detecta el *component-scan*
existente. **No** modifica `LoginController`, `UsuarioServiceImpl`,
`UsuarioDaoImpl`, `SessionFilter` ni `MainPanelController`.

Expone dos entradas:

- **`GET /sso-callback?t=<jwt>`** (flujo REAL): valida el JWT y crea la sesión.
- **`GET /sso-mock-login`** (MOCK de pruebas): identidad fija (DNI de prueba), sin
  autenticación. **No debe quedar habilitado en producción.** Se conserva como
  atajo de diagnóstico.

Ambas comparten el método `crearSesionYEntrar(dni, request)` con los pasos:

1. Verificar `ES_USUARIO` activo en `IDOSGD.SEG_USUARIOS1` (query directa por
   `dataSource1`).
2. `getDepUsuario(dni)` → empleado/dependencia.
3. `getRptaAplicativo(..., "9")` → acceso al aplicativo.
4. `getParametrosGlobales(...)`.
5. `obtienePermisos(..., "9")` → menú / accesos.
6. `getConfig(...)` → `UsuarioConfigBean`.
7–10. Crear `HttpSession`, poner atributos `"usuario"` y `"usuarioConfig"`,
   registrar el acceso.
11. **`forward:/1.0.0/mainpanel.do`** (ver nota crítica en §4.4).

**Validación del JWT (a mano, compatible con Java 6):**
- Split en 3 partes; HMAC-SHA256 de `header.payload` con `javax.crypto.Mac`;
  comparación en tiempo constante.
- Base64 url-safe con **commons-codec** (`Base64.decodeBase64`, acepta `-` y `_`).
- Parseo de header/payload con **jackson-mapper-asl** (`org.codehaus.jackson`).
- Comprueba `alg=HS256`, `iss`, `aud`, `exp` (con *skew*), `jti` único.
- **Sin dependencias nuevas:** `commons-codec`, `jackson-mapper-asl` y `javax.crypto`
  ya estaban disponibles en el SGD.

Errores → `redirect:/login.do?ssoError=<motivo>`:
`config | token | firma | alg | iss | aud | exp | expirado | jti | replay | sinDni`
y los del flujo del SGD: `inactivo | sinDatos | sinAcceso | parametros | sinConfig`.

### 4.2 Bridge — `sgd-sso-bridge/`

- `src/server.js`: en `/bridge/callback`, tras validar el ID token, si el SGD está
  configurado (`SGD_SHARED_SECRET` + `SGD_CALLBACK_URL`), firma el JWT con
  `jsonwebtoken` y redirige a `SGD_CALLBACK_URL?t=<jwt>`. Si no, muestra el HTML
  de claims (modo depuración de Etapa 1). El host de escucha es configurable con
  `BIND_HOST` (default `127.0.0.1`; `0.0.0.0` para exponer en red).
- `src/config.js`: carga y valida variables de entorno. Expone `sgdSharedSecret`,
  `sgdCallbackUrl`, `sgdTokenTtl` (60 s por defecto), `sgdTokenIssuer`
  (`"sgd-sso-bridge"`), `sgdTokenAudience` (`"sgd"`).
- `src/oidc.js`: discovery + JWKS vía openid-client v6.
- Dependencia nueva: `jsonwebtoken`.

### 4.3 SSO — Portal de sistemas

- **Migración** `database/migrations/2026_10_07_000001_add_launch_url_to_clients.php`:
  añade `clients.launch_url` y `clients.description` (nullable). El `launch_url`
  es la **URL de inicio del flujo** del sistema (p. ej. `/bridge/login` del
  Bridge), que **no** es el `redirect_uri` (ese es el callback OIDC).
- **Ruta `/`** (`routes/web.php`): pasa a la vista los clientes `active` con
  `launch_url`, ordenados por nombre.
- **Vista** `resources/views/home.blade.php`: sección "Tus sistemas" con tarjetas
  (`.system-card`); cada una enlaza al `launch_url`. Si un cliente no tiene
  `launch_url` o está inactivo, no aparece.
- **Estilos** en `public/css/sso.css` (`.systems-grid`, `.system-card`, …).
- **Tests** `tests/Feature/HomePortalTest.php` (3, en verde). La gestión del
  `launch_url` desde el **admin UI** quedó **pendiente** (hoy se puebla por
  migración / BD).

### 4.4 Notas críticas aprendidas durante la implementación

Estos dos puntos costaron depuración y son clave para no repetir el error:

1. **`ES_USUARIO = 'A'`, no `'1'`.** En `SEG_USUARIOS1` el estado "activo" se
   representa con `'A'`. El controlador compara contra la constante
   `ES_USUARIO_ACTIVO = "A"`.

2. **`forward:` + ruta de un solo segmento (estilos del panel).** El panel del SGD
   referencia su CSS/JS con rutas **relativas sin barra inicial**
   (`resources-<ver>/css/...`). Por eso:
   - Se usa **`forward:`** (no `redirect:`): la URL del navegador no cambia, así
     que su "directorio base" sigue siendo el context root `/sgd/` y los recursos
     resuelven en `/sgd/resources-<ver>/...`. Es el mismo patrón que usa el
     `LoginController` real (`forward:/{version}/mainpanel.do`, con la barra
     quedándose en `/sgd/login.do`).
   - La ruta de entrada es de **un solo segmento** (`/sso-callback`,
     `/sso-mock-login`, con guion, **no** `/sso/callback`): un segundo segmento
     haría que la base fuese `/sgd/sso/` y los CSS darían 404.
   - El `SessionFilter` intercepta solo `*.do`; rutas sin `.do` lo esquivan, lo
     que permite entrar sin sesión previa, crearla y recién entonces hacer el
     forward al panel (que sí pasa por el filtro, ya con sesión válida).

---

## 5. Configuración de referencia (piloto en red local)

> IP de la PC de desarrollo (SSO + Bridge): **`192.168.50.223`**.
> SGD en el servidor: **`192.168.90.12:8080`**.

### SSO — `sso/.env`
```env
APP_URL=http://192.168.50.223:8085
SSO_ISSUER=http://192.168.50.223:8085
```

### Bridge — `sgd-sso-bridge/.env`
```env
SSO_ISSUER=http://192.168.50.223:8085
SSO_CLIENT_ID=049bd0a8-b369-463d-8e62-f91c3f3f62d3
SSO_CLIENT_SECRET=<secreto del cliente registrado en el SSO>
PORT=8095
BIND_HOST=0.0.0.0
BRIDGE_REDIRECT_URI=http://192.168.50.223:8095/bridge/callback
SSO_SCOPES=openid profile dni
SESSION_SECRET=<aleatorio>

# Traspaso al SGD
SGD_SHARED_SECRET=<mismo secreto que en Payara -DSGD_SSO_SHARED_SECRET>
SGD_CALLBACK_URL=http://192.168.90.12:8080/sgd/sso-callback
SGD_TOKEN_TTL_SECONDS=60
```

### SGD — Payara (opción de JVM)
```
-DSGD_SSO_SHARED_SECRET=<mismo secreto que SGD_SHARED_SECRET del Bridge>
```

### Cliente registrado en el SSO (tabla `clients`)
- `code = sgd-bridge`, `client_id = 049bd0a8-b369-463d-8e62-f91c3f3f62d3`
- `redirect_uri = http://192.168.50.223:8095/bridge/callback` (coincidencia exacta)
- `launch_url = http://192.168.50.223:8095/bridge/login`

> ⚠️ La validación del admin UI solo permite `redirect_uri` por HTTP para
> `localhost`/`127.0.0.1`. Para IPs de red se actualiza el `redirect_uri`
> directamente en la BD (el admin UI lo rechazaría).

---

## 6. Proceso de despliegue (completo, sin omitir detalles)

Secuencia para dejar el piloto corriendo desde cero. Asume la topología del
piloto (SSO + Bridge en una PC de la red; SGD en el servidor Payara).

### Paso 0 — Generar el secreto compartido
```powershell
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```
Guarda ese valor: va **idéntico** en el Bridge y en Payara.

### Paso 1 — Base de datos del SSO (PostgreSQL)
```powershell
cd C:\Users\PracticanteOTI\Desktop\SSO-MDSJ
docker compose --env-file sso/.env up -d postgres     # Postgres en :55432
cd sso
.\dev.ps1 migrate                                      # aplica migraciones (incl. launch_url)
```

### Paso 2 — Registrar/verificar el cliente del SGD en el SSO
El cliente `sgd-bridge` debe existir con:
- `redirect_uri` = `http://<IP>:8095/bridge/callback`
- `launch_url` = `http://<IP>:8095/bridge/login`
- `active = true`

Si cambió la IP, actualizar `redirect_uri` y `launch_url` en la BD (tabla
`clients` / `client_redirect_uris`).

### Paso 3 — Configurar los `.env`
- `sso/.env`: `APP_URL` y `SSO_ISSUER` con la IP y puerto del SSO.
- `sgd-sso-bridge/.env`: issuer, `BRIDGE_REDIRECT_URI`, `BIND_HOST`,
  `SGD_SHARED_SECRET`, `SGD_CALLBACK_URL`, `SGD_TOKEN_TTL_SECONDS` (ver §5).

Tras cambiar el issuer, **limpiar la caché de configuración del SSO**:
```powershell
cd sso
php -d extension=pdo_pgsql artisan config:clear
```

### Paso 4 — Instalar dependencias del Bridge
```powershell
cd sgd-sso-bridge
npm install        # incluye jsonwebtoken
```

### Paso 5 — Compilar el JAR del SGD (requiere JDK 8)
```bash
cd sgd-sso-bridge-java
bash build.sh      # produce dist/sgd-sso-bridge-java.jar (bytecode Java 6)
```
`build.sh` usa JDK 8 con `-source/-target 1.6` e incluye `rt.jar` **y `jce.jar`**
en el bootclasspath (necesario para `javax.crypto`). Ajustar en el script
`JDK8_HOME`, `SGD_LIB` (carpeta `WEB-INF/lib` del SGD) y `SERVLET_API` según la
máquina.

### Paso 6 — Desplegar en el SGD (Payara)
1. **Secreto como opción de JVM** (consola `:4848` → *Configurations →
   server-config → JVM Settings → JVM Options*, o `asadmin create-jvm-options`):
   ```
   -DSGD_SSO_SHARED_SECRET=<secreto del Paso 0>
   ```
2. **Copiar el JAR** `dist/sgd-sso-bridge-java.jar` a
   `<app-sgd-desplegada>/WEB-INF/lib/`.
3. **Reiniciar el dominio** de Payara (recoge la opción de JVM y recarga la app).
   El *component-scan* detecta el controlador solo; no hay que tocar ningún XML.

### Paso 7 — Arrancar SSO y Bridge
```powershell
# SSO — en red local (escucha en 0.0.0.0)
cd sso
.\dev.ps1 serve-net        # usar 'serve' para solo-local (127.0.0.1)

# Bridge — en otra terminal
cd sgd-sso-bridge
npm start                  # escucha según BIND_HOST
```

### Paso 8 — Firewall (si se prueba desde otras PCs)
Las PCs cliente deben alcanzar los puertos **8085** (SSO) y **8095** (Bridge) de
la PC de desarrollo. Si el firewall los bloquea, crear reglas de entrada TCP para
esos puertos (requiere administrador). En el piloto el acceso ya funcionaba.

### Paso 9 — Prueba end-to-end
Desde cualquier PC de la red:
```
http://192.168.50.223:8085/
```
Login → portal "Tus sistemas" → clic en la tarjeta del SGD → debe abrir el SGD
**ya logueado** en `http://192.168.90.12:8080/sgd/1.0.0/mainpanel.do`, con menú y
configuración del usuario, **con estilos**.

### Verificación rápida de salud
```powershell
# Discovery del SSO (el issuer debe ser la IP/puerto correctos)
Invoke-WebRequest http://192.168.50.223:8085/.well-known/openid-configuration

# Salud del Bridge (issuer y clientId)
Invoke-WebRequest http://192.168.50.223:8095/bridge/health
```

---

## 7. Diagnóstico (`/login.do?ssoError=<motivo>`)

Cuando el SGD rechaza el acceso, redirige con un motivo:

| `ssoError` | Significado | Dónde mirar |
|---|---|---|
| `config` | Falta `SGD_SSO_SHARED_SECRET` en Payara | No se recogió el `-D` o no se reinició el dominio |
| `token` | JWT mal formado | El Bridge no generó bien el token |
| `firma` | Firma inválida | El secreto del Bridge ≠ el de Payara |
| `alg` | Algoritmo no es HS256 | Desajuste de configuración |
| `iss` / `aud` | Emisor/audiencia inesperados | `sgdTokenIssuer`/`sgdTokenAudience` del Bridge vs `EXPECTED_ISS`/`EXPECTED_AUD` del SGD |
| `expirado` | Token vencido (>60 s) | Relojes desincronizados o demora en el salto |
| `replay` | JWT reutilizado (un solo uso) | Se recargó la URL del callback |
| `sinDni` | El JWT no trae `dni` | Falta el scope `dni` en el SSO |
| `inactivo` | `ES_USUARIO` ≠ `'A'` o query falló | `SEG_USUARIOS1.ES_USUARIO` / acceso a `dataSource1` |
| `sinDatos` | `getDepUsuario` devolvió null | El DNI no resuelve empleado/dependencia |
| `sinAcceso` | `getRptaAplicativo` ≠ `"00"` | `SEG_USER_APLICA` con `COD_APLICA=9` |
| `parametros` | Excepción en `getParametrosGlobales` | Logs del SGD (`server.log`) |
| `sinConfig` | `getConfig` devolvió null | `TDTX_CONFIG_EMP` |

**Si el panel abre sin estilos:** la URL del navegador tiene un segmento de más
(no es de un solo segmento bajo `/sgd/`), o se usó `redirect:` en vez de
`forward:`. Ver §4.4.

---

## 8. Pendientes y recomendaciones para producción

- **Quitar el endpoint `/sso-mock-login`** antes de producción (entra sin
  autenticación; es solo para diagnóstico).
- **HTTPS en todo**: issuer, `redirect_uri`, `SGD_CALLBACK_URL`, cookies con
  `secure=true`.
- **Anti-replay con múltiples nodos**: hoy los `jti` usados se guardan **en
  memoria** (suficiente para un único nodo Payara). Si se escala a clúster,
  migrar a una tabla/almacén compartido.
- **Gestión de `launch_url`/`description` desde el admin UI** del SSO (hoy se
  puebla por migración/BD).
- **Despliegue estable del SSO y el Bridge** (hoy se levantan a mano en terminales
  de desarrollo). Para producción, servirlos como servicios (p. ej. detrás de un
  reverse proxy con HTTPS).
- **Rotación del secreto compartido** y plan de gestión de claves.

---

## 9. Archivos clave (referencia rápida)

| Archivo | Qué es |
|---|---|
| `sgd-sso-bridge-java/src/.../SsoBridgeController.java` | Controlador del SGD: `/sso-callback` y `/sso-mock-login` |
| `sgd-sso-bridge-java/build.sh` | Compila el JAR (JDK 8, target 1.6, incluye `jce.jar`) |
| `sgd-sso-bridge/src/server.js` | Bridge: callback OIDC + firma del JWT + redirección al SGD |
| `sgd-sso-bridge/src/config.js` | Configuración del Bridge (env vars) |
| `sgd-sso-bridge/src/oidc.js` | Discovery / JWKS con openid-client v6 |
| `sso/routes/web.php` | Ruta `/` que alimenta el portal |
| `sso/resources/views/home.blade.php` | Portal "Tus sistemas" |
| `sso/public/css/sso.css` | Estilos (incl. tarjetas de sistemas) |
| `sso/database/migrations/2026_10_07_000001_add_launch_url_to_clients.php` | Columna `launch_url` |
| `sso/tests/Feature/HomePortalTest.php` | Tests del portal |
| `sso/dev.ps1` | Tareas de desarrollo (`serve`, `serve-net`, `migrate`, `test`, …) |
