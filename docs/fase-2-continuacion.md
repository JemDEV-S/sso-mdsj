# Fase 2 — Guía de continuación (retomar en otra sesión)

> Documento para continuar la integración SSO ↔ SGD sin reconstruir el contexto.
> Última actualización: 2026-10-07.

---

## 1. Dónde estamos

Integración del **SGD legacy** (Payara/GlassFish, bytecode Java 6, Spring
3.2.6, context root `/sgd`) como primer cliente del **SSO municipal** (Laravel
13 + PostgreSQL, OIDC Authorization Code, RS256), **sin reescribir** la
autenticación/autorización interna del SGD.

### Arquitectura objetivo

```text
Navegador
  → SGD (/sso/login)                  [controlador Java nuevo]
  → Bridge Node (OIDC con el SSO)     [sgd-sso-bridge/, ya funciona]
  → callback: valida ID token, obtiene DNI/sub
  → entrega identidad al SGD mediante credencial de corta duración   ← FALTA DISEÑAR
  → SGD SsoBridgeController: resuelve COD_USER, reutiliza servicios, crea HttpSession
  → /sgd/1.0.0/mainpanel.do
```

Mapeo de identidad: `DNI SSO → COD_USER SGD`. Aplicativo SGD: `COD_APLICA = "9"`.

### Estado por etapas

| Etapa | Estado |
|---|---|
| **Etapa 1** — Bridge Node OIDC (login→callback→token→validación→identidad) | ✅ COMPLETADA y probada en navegador (2026-10-07) |
| **Etapa 2 · Paso 1** — Mock de sesión Java en el SGD (`SsoBridgeController`) | ✅ COMPLETADA y probada en el servidor (2026-10-07). Crea sesión y abre el panel con estilos en `http://192.168.90.12:8080/sgd/sso-mock-login` |
| **Etapa 2 · Paso 2** — Traspaso seguro Bridge→SGD (credencial corta duración) | ⬜ No iniciado (solo diseñado conceptualmente en el doc de contexto) |

---

## 2. TAREA INMEDIATA de la próxima sesión

**Diseñar e implementar la Etapa 2 · Paso 2: el traspaso seguro Bridge Node →
SGD** (ver §6). El mock ya funciona; el siguiente objetivo es reemplazar el DNI
fijo por la identidad real que entrega el Bridge tras validar el ID token, sin
exponer el DNI por URL.

### Estado del mock (ya probado — referencia)

El mock `SsoBridgeController` quedó **desplegado y funcionando** en el servidor
de pruebas. URL de prueba (⚠️ cambió a un solo segmento con guion):

```text
http://192.168.90.12:8080/sgd/sso-mock-login
```

Crea la sesión del SGD (DNI `73129103`) y abre el panel **con estilos**.

Para redesplegarlo si hiciera falta:

1. **Llevar el JAR al servidor** — copiar
   `sgd-sso-bridge-java/dist/sgd-sso-bridge-java.jar` a
   `<app-sgd>/WEB-INF/lib/sgd-sso-bridge-java.jar`. (Recompilar con
   `sgd-sso-bridge-java/build.sh`, requiere **JDK 8**.)
2. **Recargar / reiniciar** la app `sgd` en Payara (el `component-scan` de
   `pe.gob.onpe.tramitedoc` lo detecta solo; no hay que tocar XML).
3. **Probar** en `http://192.168.90.12:8080/sgd/sso-mock-login`.

### Dos aprendizajes del despliegue (ya resueltos)

- **`ES_USUARIO = 'A'` para activo**, no `'1'` (el doc original asumía `'1'`).
  El controlador compara contra la constante `ES_USUARIO_ACTIVO = "A"`.
- **El panel usa rutas relativas para sus CSS/JS** (`resources-<ver>/css/...`,
  sin barra inicial). Para que carguen, la URL servida debe tener como base el
  context root `/sgd/`. Por eso el controlador usa **`forward:`** (no
  `redirect:`) y una ruta de **un solo segmento** (`/sso-mock-login`): así la
  barra del navegador se queda en `/sgd/sso-mock-login` (base `/sgd/`). Es el
  mismo patrón del `LoginController` real, que hace
  `forward:/{version}/mainpanel.do` y mantiene la URL en `/sgd/login.do`. Un
  `redirect` a `/sgd/1.0.0/mainpanel.do` rompe los estilos (CSS 404).

### Diagnóstico si falla

El controlador redirige a `/login.do?ssoError=<motivo>`:

| `ssoError` | Significado | Dónde revisar |
|---|---|---|
| `inactivo` | `ES_USUARIO` ≠ "1" o query falló | `SEG_USUARIOS1.ES_USUARIO` del DNI; acceso a `dataSource1` |
| `sinDatos` | `getDepUsuario` devolvió null | El DNI no resuelve empleado/dependencia |
| `sinAcceso` | `getRptaAplicativo` ≠ "00" | `SEG_USER_APLICA` con `COD_APLICA=9` para ese usuario |
| `parametros` | excepción en `getParametrosGlobales` | logs del SGD |
| `sinConfig` | `getConfig` devolvió null | `TDTX_CONFIG_EMP` |

Errores posibles al desplegar: conflicto de inyección de beans (revisar que
`usuarioService`, `usuarioDao`, `usuarioConfigService`, `dataSource1` existan con
esos nombres — ya verificado en el contexto), o que el esquema `IDOSGD` difiera.
Si aparece un stack trace, capturarlo y ajustar.

### Aviso de seguridad

`/sso/mock-login` entra con un DNI fijo **sin autenticación**. Es solo para
validar la creación de sesión. **No debe quedar habilitado en producción.** Se
reemplazará por el callback seguro cuando se conecte el Bridge real.

---

## 3. Componentes y rutas (referencia)

### Repositorio `c:\Users\PracticanteOTI\Desktop\SSO-MDSJ\`

| Carpeta | Qué es |
|---|---|
| `sso/` | SSO Laravel 13. Issuer `http://127.0.0.1:8085`. |
| `sgd-sso-bridge/` | Bridge **Node.js** OIDC (Etapa 1). Corre en `:8095`. |
| `sgd-sso-bridge-java/` | Componente **Java** del SGD (Etapa 2). Contiene el `SsoBridgeController`. |
| `docs/` | `fase-1.md`, `contexto_integracion_sso_sgd.md`, este archivo. |

### Copia local del SGD (solo lectura, para firmas)

```text
D:\APP-SGD\sgd\
  WEB-INF\lib\  → onpe-autentica-1.0.jar, tramitedoc-logic-1.0.jar, libreria-onpe-1.0.jar
  PANORAMA_SGD.md  → análisis de la copia
```

### Herramientas Java en esta PC

- **JDK 8 (Temurin):** `C:\Program Files\Eclipse Adoptium\jdk-8.0.504.1-hotspot\`
  — usar para compilar/`javap` (target 1.6). `javap` no está en PATH; invocar
  por ruta completa.
- JDK 24 en el PATH: **no** sirve para compilar (no genera bytecode Java 6).
- `javax.servlet-api` 3.1 descargada en `/tmp/compile-libs/servlet-api.jar`
  (solo para compilar; **no** se empaqueta). Si no existe, volver a bajarla de
  Maven Central: `javax/servlet/javax.servlet-api/3.1.0`.

---

## 4. Cómo levantar el SSO y el Bridge Node (si se necesitan)

```powershell
# SSO (desde la raíz del repo)
docker compose --env-file sso/.env up -d postgres     # PostgreSQL en :55432
cd sso; .\dev.ps1 serve                                # PHP en :8085

# Bridge Node (OIDC)
cd sgd-sso-bridge; npm start                           # :8095, prueba en /bridge/login
```

- Cliente registrado en el SSO para el Bridge: `code = sgd-bridge`,
  `client_id = 049bd0a8-b369-463d-8e62-f91c3f3f62d3`, redirect
  `http://127.0.0.1:8095/bridge/callback`. Secretos en `sgd-sso-bridge/.env`.
- Usuario de prueba: DNI `73129103` (Jemy Huaman), contraseña `Jemy1506`,
  `sub = 130436fb-fefd-467d-81ae-ea33f08caf0c`.

---

## 5. Firmas Java reales usadas por el controlador (verificadas con javap)

Todas confirmadas contra los JAR del SGD; el controlador compila limpio.

```java
// UsuarioService (bean "usuarioService")
DatosUsuario getDepUsuario(String coUsuario)
void obtienePermisos(Mensaje, Usuario, String coAplicativo)
String registraLogAcceso(Usuario, String)          // "1" éxito

// UsuarioDao (bean "usuarioDao")
String getRptaAplicativo(Mensaje, Usuario, String coAplicativo)   // "00" éxito
void getParametrosGlobales(Mensaje, Usuario) throws Exception

// UsuarioConfigService (bean "usuarioConfigService")
UsuarioConfigBean getConfig(Mensaje, String cempCodemp, String coDep)

// Mensaje: getCoRespuesta() == "00" => OK
// Usuario: setCoUsuario, setNuDni, setCempCodemp, setCoDep, setInAD,
//          setInAdmin, setSessionId, setUsuarioAccesos, getInAdmin, getNuDni
// UsuarioConfigBean: setCoUsuario, setInEsAdmin, getCoDep
```

`SessionFilter` exige en la sesión el atributo `"usuario"` (tipo `Usuario`) con
`getNuDni()` y `getSessionId()` **no vacíos**, más `"usuarioConfig"`. Por eso el
callback va **sin** `.do` (`/sso/mock-login`): entra sin ser bloqueado, crea la
sesión y recién redirige a `mainpanel.do` (que sí pasa por el filtro, ya válido).

---

## 6. Siguiente paso después del mock (Etapa 2 · Paso 2)

Cuando el mock funcione, diseñar el **traspaso seguro Bridge Node → SGD**:

- El Bridge valida el ID token (ya lo hace) y necesita entregar la identidad al
  `SsoBridgeController` de forma que el SGD **confíe** sin exponer el DNI por URL
  (anti-patrón prohibido, ver `contexto_integracion_sso_sgd.md` §23).
- Opción típica: el Bridge emite una **credencial de corta duración y un solo
  uso** (token firmado o referencia opaca en almacén compartido con TTL corto),
  que el SGD canjea/valida en el callback antes de crear la sesión.
- Decisiones a tomar: formato de la credencial (JWT firmado vs token opaco),
  dónde se valida, cómo se comparte la clave/almacén, TTL, un solo uso.
- Entonces `/sso/mock-login` se sustituye por `/sso/callback` real y se elimina
  el DNI fijo.

---

## 7. Memorias relacionadas (ya guardadas)

- `fase2-sgd-sso-bridge` — estado general de la Fase 2.
- `sgd-firmas-java` — firmas javap detalladas.
- `no-relajar-tls-produccion` — requisito de seguridad.
