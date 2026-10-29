# sgd-sso-bridge-java

Componente **Java** que se despliega **dentro del SGD** (a diferencia de
`sgd-sso-bridge/`, que es el Bridge Node OIDC). Contiene el controlador Spring
que construye la `HttpSession` del SGD reutilizando sus servicios existentes.

## Estado: ETAPA 2 · PASO 1 — MOCK

`SsoBridgeController` expone `GET /sso/mock-login`. Con un **DNI de prueba fijo**
(constante `DNI_PRUEBA`, sin Bridge OIDC todavía), reconstruye la sesión del SGD
igual que el login tradicional y redirige a `/1.0.0/mainpanel.do`.

El objetivo es comprobar **una sola cosa**: que un controlador nuevo puede crear
una sesión que el `SessionFilter` acepte, reutilizando `UsuarioService`,
`UsuarioDao` y `UsuarioConfigService`, **sin modificar** ningún JAR original ni
`LoginController`/`SessionFilter`/`MainPanelController`.

### Qué hace, paso a paso

1. Verifica `ES_USUARIO` activo (query directa por `dataSource1` sobre
   `IDOSGD.SEG_USUARIOS1`).
2. `usuarioService.getDepUsuario(dni)` → empleado/dependencia.
3. `usuarioDao.getRptaAplicativo(msg, usuario, "9")` → acceso al aplicativo SGD.
4. `usuarioDao.getParametrosGlobales(msg, usuario)` → nombres/DNI/local.
5. `usuarioService.obtienePermisos(msg, usuario, "9")` → menú/permisos.
6. `usuarioConfigService.getConfig(msg, cempCodemp, coDep)` → `UsuarioConfigBean`.
7. Crea `HttpSession`, `setSessionId`, guarda `"usuario"` y `"usuarioConfig"`.
8. `registraLogAcceso(usuario, "1")` y `redirect:/1.0.0/mainpanel.do`.

## ANTES DE COMPILAR — editar el DNI de prueba

En
[src/.../SsoBridgeController.java](src/pe/gob/onpe/tramitedoc/web/controller/SsoBridgeController.java),
reemplazar:

```java
private static final String DNI_PRUEBA = "CAMBIAR_ESTE_DNI";
```

por un **DNI real** que exista en `SEG_USUARIOS1` y tenga acceso a
`COD_APLICA = 9`. El DNI nunca se lee de la URL (anti-patrón de seguridad).

## Compilar

Requiere **JDK 8** (el bytecode debe ser Java 6; un JDK moderno no genera
`-target 1.6`). La Servlet API se usa solo para compilar y **no** se empaqueta.

```bash
# Ajusta las rutas si difieren en tu equipo:
export JDK8_HOME="/c/Program Files/Eclipse Adoptium/jdk-8.0.504.1-hotspot"
export SGD_LIB="D:/APP-SGD/sgd/WEB-INF/lib"
export SERVLET_API="/tmp/compile-libs/servlet-api.jar"   # javax.servlet-api 3.1

./build.sh
# -> dist/sgd-sso-bridge-java.jar  (una sola clase, bytecode major version 50)
```

Verificado localmente: compila sin errores y el `.class` es `major version: 50`
(Java 6).

## Desplegar en el servidor de pruebas

1. Copiar `dist/sgd-sso-bridge-java.jar` a:

   ```text
   <sgd>/WEB-INF/lib/sgd-sso-bridge-java.jar
   ```

2. Reiniciar / redesplegar la aplicación `sgd` en Payara. El `component-scan`
   de `pe.gob.onpe.tramitedoc` detectará el controlador automáticamente.

3. Probar en el navegador:

   ```text
   https://<host>/sgd/sso/mock-login
   ```

   Si el usuario de prueba está activo y tiene acceso al aplicativo 9, debería
   crearse la sesión y abrirse `/sgd/1.0.0/mainpanel.do` con su menú y config.

### Si algo falla

El controlador redirige a `/login.do?ssoError=<motivo>` con estos motivos:
`inactivo` (ES_USUARIO), `sinDatos` (getDepUsuario null), `sinAcceso`
(getRptaAplicativo ≠ "00"), `parametros`, `sinConfig`. Útil para diagnosticar.

## No incluido todavía (pasos siguientes)

- El Bridge OIDC real (`sgd-sso-bridge/` Node) **no** está conectado aún.
- El traspaso seguro Bridge → SGD (credencial de corta duración) se diseña después.
- Este mock **no** debe quedar habilitado en producción: `/sso/mock-login`
  entra con un DNI fijo sin autenticación. Es solo para validar la sesión.
