# Contexto técnico para futura integración SSO con el SGD

## 1. Objetivo

Documentar el análisis realizado sobre el **Sistema de Gestión Documental (SGD)** desplegado en Payara/GlassFish, con el fin de dejar preparado el contexto técnico para una futura integración con un **Single Sign-On (SSO)**.

El objetivo de la integración será permitir un flujo similar al siguiente:

```text
Usuario inicia sesión en el SSO
        ↓
Portal SSO muestra las aplicaciones disponibles
        ↓
Usuario selecciona SGD
        ↓
SGD valida la identidad entregada por el SSO
        ↓
SGD identifica al usuario local
        ↓
SGD carga permisos y configuración existentes
        ↓
SGD crea su sesión local
        ↓
Usuario ingresa al panel principal
```

La idea principal es **no reescribir el SGD ni reemplazar su autorización interna**, sino agregar un puente de autenticación que permita reutilizar la lógica existente de usuarios, permisos, dependencias y configuración.

---

## 2. Estado actual del SGD

La aplicación analizada se encuentra desplegada como una aplicación web Java tradicional sobre Payara/GlassFish.

### Aplicación

- Nombre de aplicación: `sgd`
- Context root: `/sgd`
- Ruta desplegada:

```text
C:\server\glassfish\domains\domain1\applications\sgd\
```

### Estructura principal

```text
sgd/
├── applet/
├── META-INF/
├── reports/
├── WEB-INF/
├── abrirConsulta.jsp
├── index.jsp
└── muestra.jsp
```

Dentro de `WEB-INF` se identificó:

```text
WEB-INF/
├── classes/
├── config/
├── i18n/
├── lib/
├── spring/
├── tags/
├── templates/
├── views/
├── glassfish-web.xml
├── web.xml
└── weblogic.xml
```

---

## 3. Tecnologías identificadas

### Java

El servidor ejecuta Java 8:

```text
C:\Program Files\Java\jdk1.8.0_202
```

Sin embargo, las clases originales del SGD tienen:

```text
major version: 50
```

Esto corresponde a **Java 6**.

Para mantener máxima compatibilidad con el sistema existente, cualquier JAR adicional debería compilarse inicialmente con:

```text
-source 1.6
-target 1.6
```

siempre que las dependencias utilizadas lo permitan.

### Spring

La aplicación utiliza:

```text
Spring 3.2.6.RELEASE
```

JARs encontrados:

```text
spring-aop-3.2.6.RELEASE.jar
spring-beans-3.2.6.RELEASE.jar
spring-context-3.2.6.RELEASE.jar
spring-core-3.2.6.RELEASE.jar
spring-expression-3.2.6.RELEASE.jar
spring-jdbc-3.2.6.RELEASE.jar
spring-tx-3.2.6.RELEASE.jar
spring-web-3.2.6.RELEASE.jar
spring-webmvc-3.2.6.RELEASE.jar
```

Por lo tanto, cualquier nuevo componente debe evitar APIs modernas de Spring y mantenerse compatible con Spring 3.2.x.

---

## 4. JARs principales del SGD

Se identificaron como componentes relevantes:

```text
tramitedoc-logic-1.0.jar
onpe-autentica-1.0.jar
libreria-onpe-1.0.jar
```

### Responsabilidades observadas

`tramitedoc-logic-1.0.jar` contiene principalmente:

- controladores web;
- servicios del SGD;
- configuración de usuario;
- menú;
- lógica funcional.

`onpe-autentica-1.0.jar` contiene principalmente:

- modelo `Usuario`;
- modelo `UsuarioAcceso`;
- `UsuarioService`;
- `UsuarioServiceImpl`;
- `UsuarioDao`;
- `UsuarioDaoImpl`;
- lógica tradicional de autenticación.

`libreria-onpe-1.0.jar` contiene utilitarios comunes, entre ellos manejo de sesión y cifrado.

---

## 5. Configuración Spring

En:

```text
WEB-INF/config/applicationContext.xml
```

se encontró:

```xml
<context:component-scan base-package="pe.gob.onpe.tramitedoc"/>
<context:component-scan base-package="pe.gob.onpe.tramiteconv.dao.impl.sqlserver"/>
```

Esto permite que un nuevo controlador agregado bajo:

```text
pe.gob.onpe.tramitedoc...
```

sea detectado automáticamente por Spring si utiliza anotaciones como `@Controller`.

El `web.xml` carga:

```text
/WEB-INF/config/applicationContext*.xml
```

Por lo tanto también se carga automáticamente:

```text
applicationContext-ds.xml
```

---

## 6. Beans existentes que pueden reutilizarse

### usuarioService

Está definido explícitamente:

```xml
<bean class="pe.gob.onpe.autentica.service.impl.UsuarioServiceImpl"
      name="usuarioService"
      p:usuarioDao-ref="usuarioDao"/>
```

Puede inyectarse desde un nuevo controlador:

```java
@Autowired
private UsuarioService usuarioService;
```

### usuarioConfigService

`UsuarioConfigServiceImpl` está anotado como:

```java
@Service("usuarioConfigService")
```

Puede inyectarse:

```java
@Autowired
private UsuarioConfigService usuarioConfigService;
```

### usuarioDao

Está definido explícitamente:

```xml
<bean class="pe.gob.onpe.autentica.dao.impl.sqlserver.UsuarioDaoImpl"
      name="usuarioDao">
    <constructor-arg name="dataSource" ref="dataSource1" />
</bean>
```

Por lo tanto también puede reutilizarse:

```java
@Autowired
private UsuarioDao usuarioDao;
```

Esto es especialmente importante porque permite utilizar ciertas validaciones y cargas internas sin ejecutar la autenticación tradicional por contraseña.

---

## 7. DataSources

Se identificaron:

```xml
<jee:jndi-lookup id="dataSource" jndi-name="${jndi.main.jndiName}"/>
<jee:jndi-lookup id="dataSource1" jndi-name="${jndi.sec.jndiName}"/>
<jee:jndi-lookup id="dataSourceQry" jndi-name="${jndi.qry.jndiName}"/>
```

`UsuarioDaoImpl` utiliza:

```text
dataSource1
```

Es la conexión relacionada con autenticación/seguridad.

Si fuese necesario ejecutar una consulta adicional no expuesta por `UsuarioDao`, puede reutilizarse:

```java
@Resource(name = "dataSource1")
private DataSource dataSource1;
```

El servidor dispone de:

```text
javax.annotation.Resource
```

y:

```text
javax.servlet.*
```

aunque algunos JAR del servidor utilicen nombres de archivo con prefijo `jakarta`.

---

## 8. Flujo actual de autenticación

El flujo identificado es aproximadamente:

```text
LoginController
    ↓
UsuarioServiceImpl.autenticarUsuario()
    ↓
UsuarioDaoImpl.getRptaIdentificacion()
    ↓
validación local o Active Directory
    ↓
UsuarioDaoImpl.getRptaAplicativo()
    ↓
UsuarioDaoImpl.getParametrosGlobales()
    ↓
UsuarioServiceImpl.obtienePermisos()
    ↓
UsuarioConfigServiceImpl.getConfig()
    ↓
creación de sesión
    ↓
/1.0.0/mainpanel.do
```

---

## 9. LoginController

El controlador existente está anotado como:

```java
@Controller("loginController")
@RequestMapping("/login.do")
@SessionAttributes({"usuario", "usuarioConfig"})
```

Los atributos principales que terminan en la sesión son:

```text
usuario
usuarioConfig
```

En el login exitoso también se asigna:

```java
usuario.setSessionId(...)
```

Posteriormente se hace:

```text
forward:/{version}/mainpanel.do
```

La versión configurada en el sistema es:

```text
1.0.0
```

Por lo tanto el panel normal se encuentra en:

```text
/sgd/1.0.0/mainpanel.do
```

---

## 10. SessionFilter

Existe:

```text
pe.gob.onpe.tramitedoc.web.filter.SessionFilter
```

El filtro protege principalmente las rutas:

```text
*.do
```

Para permitir el acceso verifica que exista sesión y que el objeto `usuario` tenga, entre otros:

```text
nuDni
sessionId
```

Esto es importante para SSO.

### Recomendación

El callback inicial del SSO debería usar una ruta **sin extensión `.do`**, por ejemplo:

```text
/sso/callback
```

Así podrá ingresar sin sesión previa.

Después de validar el token SSO y construir la sesión del SGD, se podrá redirigir a:

```text
/1.0.0/mainpanel.do
```

A partir de ese punto, el filtro existente seguirá funcionando normalmente.

---

## 11. MainPanelController

La ruta es:

```text
/{version}/mainpanel.do
```

El controlador recupera:

```text
usuarioConfig
```

directamente de la sesión mediante:

```java
Utilidades.loadUserConfigFromSession(request)
```

Ese método busca exactamente:

```text
usuarioConfig
```

Por lo tanto este atributo es obligatorio para una sesión creada mediante SSO.

---

## 12. Menú y permisos

La clase:

```text
pe.gob.onpe.tramitedoc.web.util.MenuOpciones
```

utiliza:

```java
usuario.getUsuarioAccesos()
```

y recorre la lista de `UsuarioAcceso`.

Por lo tanto, antes de abrir el panel, el nuevo flujo SSO debe cargar los permisos mediante:

```java
usuarioService.obtienePermisos(msg, usuario, "9");
```

o equivalentemente mediante el DAO.

No basta con crear simplemente un objeto `Usuario` y ponerlo en sesión.

---

## 13. Código de aplicación del SGD

En `application.properties` existe:

```text
co_aplicativo=712FFB3790B8E84916A9BDA43B8049B6
```

Ese valor se encuentra cifrado.

La aplicación lo descifra internamente con la lógica existente y el resultado identificado fue:

```text
9
```

Por lo tanto:

```text
COD_APLICA = 9
```

corresponde al SGD.

En la base de datos también se verificó que existen registros de acceso con:

```text
COD_APLICA = 9
```

---

## 14. Modelo de identificación local

Se comprobó que para los usuarios normales:

```text
COD_USER ≈ DNI
```

Existe al menos una excepción identificada, aparentemente correspondiente a un usuario administrativo.

Por tanto, para la integración SSO la regla principal puede ser:

```text
DNI proveniente del SSO
        ↓
COD_USER del SGD
```

pero debe contemplarse una estrategia para excepciones administrativas.

### Recomendación

No codificar una excepción directamente en Java.

Si existen usuarios especiales, mantener una pequeña tabla o configuración de correspondencia:

```text
identidad_sso → usuario_sgd
```

Solo debería utilizarse cuando DNI y `COD_USER` no coincidan.

---

## 15. getDepUsuario()

El servicio existente expone:

```java
DatosUsuario getDepUsuario(String coUsuario)
```

La consulta interna obtiene datos como:

```text
CEMP_CODEMP
CO_DEP
DE_DEP
IN_AD
```

por medio de la relación entre:

```text
SEG_USUARIOS1
RHTM_PER_EMPLEADOS
RHTM_DEPENDENCIA
```

Esto permite recuperar información esencial del usuario **sin validar contraseña**.

Para el flujo SSO puede utilizarse:

```java
DatosUsuario datos = usuarioService.getDepUsuario(dni);
```

y después copiar al objeto `Usuario` los datos necesarios.

---

## 16. Validación de acceso a la aplicación

`UsuarioDao` expone:

```java
String getRptaAplicativo(
    Mensaje mensaje,
    Usuario usuario,
    String coAplicativo
)
```

Internamente consulta:

```text
IDOSGD.SEG_USER_APLICA
```

y valida:

```text
COD_USER
COD_APLICA
ES_ACTIVO
ES_ADMIN
```

Para SGD se utilizará:

```text
coAplicativo = "9"
```

Esta validación debe mantenerse en el flujo SSO.

El hecho de que una persona haya iniciado sesión correctamente en el SSO **no implica automáticamente que tenga acceso al SGD**.

---

## 17. Parámetros globales del usuario

`UsuarioDao` expone:

```java
void getParametrosGlobales(
    Mensaje mensaje,
    Usuario usuario
)
```

Este método carga datos del empleado desde:

```text
RHTM_PER_EMPLEADOS
```

entre ellos:

```text
DNI
nombres
apellido paterno
apellido materno
local
```

Esta lógica debe reutilizarse en lugar de duplicarla.

---

## 18. Permisos internos

`UsuarioService.obtienePermisos()` termina consultando:

```text
SEG_USER_APLICA_OPC
```

con:

```text
COD_USER
COD_APLICA
ES_HABILITADO
```

El resultado se almacena en:

```java
usuario.setUsuarioAccesos(...)
```

y posteriormente el menú del SGD utiliza esa lista.

Esto significa que el SSO debe resolver únicamente la identidad.

La autorización funcional debe continuar perteneciendo al SGD.

---

## 19. UsuarioConfig

El servicio:

```java
usuarioConfigService.getConfig(
    mensaje,
    cempCodemp,
    coDep
)
```

carga la configuración funcional del trabajador.

La consulta utiliza principalmente:

```text
TDTX_CONFIG_EMP
RHTM_DEPENDENCIA
```

y obtiene información como:

```text
dependencia
siglas
local
tipo de acceso
consulta
correo
firma
mesa de partes
configuraciones documentales
imagen de portada
```

Si no existe configuración, la lógica actual puede crear una configuración básica.

Después de obtenerla, el login tradicional hace aproximadamente:

```java
config.setCoUsuario(usuario.getCoUsuario());
config.setInEsAdmin(usuario.getInAdmin());
usuario.setCoDep(config.getCoDep());
```

El flujo SSO debería replicar esta parte.

---

## 20. Problema particular: estado ES_USUARIO

La autenticación tradicional llama:

```java
getRptaIdentificacion()
```

Este método valida simultáneamente:

- existencia del usuario;
- estado del usuario;
- contraseña o AD.

Para SSO no queremos utilizar la contraseña local.

Sin embargo, la interfaz actual de `UsuarioDao` **no ofrece un método independiente para consultar únicamente `ES_USUARIO`**.

Por lo tanto se recomienda realizar una única consulta adicional utilizando `dataSource1`.

Ejemplo conceptual:

```sql
SELECT ES_USUARIO
FROM IDOSGD.SEG_USUARIOS1
WHERE COD_USER = ?
```

Solo debe permitirse el acceso si el estado corresponde a usuario activo.

Esta consulta es preferible a reutilizar `getRptaIdentificacion()` con contraseñas ficticias o mecanismos similares.

---

## 21. Flujo propuesto para el nuevo controlador SSO

El flujo recomendado es:

```text
1. Recibir callback del SSO
2. Validar criptográficamente el token/protocolo SSO
3. Extraer DNI de una identidad confiable
4. Resolver DNI → COD_USER
5. Verificar ES_USUARIO activo
6. usuarioService.getDepUsuario(COD_USER)
7. usuarioDao.getRptaAplicativo(..., "9")
8. usuarioDao.getParametrosGlobales(...)
9. usuarioService.obtienePermisos(..., "9")
10. usuarioConfigService.getConfig(...)
11. completar UsuarioConfigBean
12. completar Usuario
13. crear HttpSession
14. asignar sessionId
15. guardar usuario
16. guardar usuarioConfig
17. registrar log de acceso
18. redirigir a /1.0.0/mainpanel.do
```

---

## 22. Pseudocódigo conceptual

El código final dependerá del protocolo que se elija para el SSO, pero la parte específica del SGD sería aproximadamente:

```java
Usuario usuario = new Usuario();
usuario.setCoUsuario(dni);

// 1. verificar usuario activo
validarUsuarioActivo(dni);

// 2. recuperar empleado/dependencia
DatosUsuario datos = usuarioService.getDepUsuario(dni);

if (datos == null) {
    // rechazar acceso
}

// completar información inicial necesaria
usuario.setCempCodemp(datos.getCempCodemp());
usuario.setCoDep(datos.getCoDep());
usuario.setInAD(datos.getInAD());

// 3. validar acceso al aplicativo SGD
Mensaje msg = new Mensaje();

usuarioDao.getRptaAplicativo(msg, usuario, "9");

if (!"00".equals(msg.getCoRespuesta())) {
    // rechazar acceso
}

// 4. cargar datos generales
usuarioDao.getParametrosGlobales(msg, usuario);

// 5. permisos
usuarioService.obtienePermisos(msg, usuario, "9");

// 6. configuración
UsuarioConfigBean config =
    usuarioConfigService.getConfig(
        msg,
        usuario.getCempCodemp(),
        usuario.getCoDep()
    );

config.setCoUsuario(usuario.getCoUsuario());
config.setInEsAdmin(usuario.getInAdmin());

usuario.setCoDep(config.getCoDep());

// 7. datos de red
loadUsuarioRemoteAttribs(usuario, request);

// 8. sesión
HttpSession session = request.getSession(true);

usuario.setSessionId(session.getId());

session.setAttribute("usuario", usuario);
session.setAttribute("usuarioConfig", config);

// 9. log
usuarioService.registraLogAcceso(usuario, "1");

// 10. entrada al sistema
return "redirect:/1.0.0/mainpanel.do";
```

Este código es únicamente conceptual y deberá ajustarse después de definir el protocolo SSO.

---

# Recomendaciones para el diseño del SSO

## 23. No implementar un SSO basado solamente en DNI por URL

Debe evitarse un mecanismo como:

```text
/sgd/sso/callback?dni=12345678
```

porque cualquier usuario que pueda modificar la URL podría intentar suplantar identidades.

El SGD únicamente debe confiar en un DNI cuando haya sido obtenido de una credencial/token que haya sido validado criptográficamente.

---

## 24. Protocolo recomendado

Para un sistema nuevo, la recomendación principal es utilizar:

```text
OpenID Connect (OIDC)
```

sobre:

```text
OAuth 2.0
```

Preferentemente mediante:

```text
Authorization Code Flow
```

El flujo sería:

```text
Usuario
  ↓
SSO
  ↓ autenticación
SSO entrega authorization code
  ↓
SGD o un componente intermedio intercambia code por tokens
  ↓
validación del ID Token
  ↓
extracción del DNI
  ↓
creación de sesión local del SGD
```

---

## 25. Evitar JWT casero como protocolo completo

Un JWT firmado puede utilizarse como pieza técnica, pero no se recomienda diseñar un protocolo propio basado únicamente en:

```text
?token=<jwt>
```

sin manejar correctamente:

- expiración;
- emisor;
- audiencia;
- nonce;
- state;
- replay attacks;
- rotación de claves;
- logout;
- sesiones;
- validación de redirect URI.

OIDC ya define estos mecanismos.

---

## 26. Claims recomendados

Como mínimo, el SSO debería poder entregar:

```json
{
  "sub": "identificador-interno-inmutable",
  "dni": "12345678",
  "name": "Nombre del usuario"
}
```

Para la integración con SGD el dato principal será:

```text
dni
```

No obstante, el identificador técnico estable debe ser:

```text
sub
```

El DNI puede mantenerse como atributo de negocio utilizado para resolver al usuario en sistemas existentes.

---

## 27. Roles y permisos

Se recomienda claramente separar:

### SSO

Responsable de:

```text
¿Quién es el usuario?
```

### SGD

Responsable de:

```text
¿Qué puede hacer el usuario dentro del SGD?
```

Por tanto, inicialmente **no conviene trasladar los permisos del SGD al SSO**.

El SGD debe seguir consultando:

```text
SEG_USER_APLICA
SEG_USER_APLICA_OPC
```

De este modo se reduce el impacto de la integración y se evita duplicar reglas de autorización.

---

## 28. Estado de usuarios

Se recomienda validar dos niveles:

### Nivel SSO

El usuario debe estar habilitado para autenticarse en la organización.

### Nivel SGD

El usuario debe continuar activo localmente:

```text
SEG_USUARIOS1.ES_USUARIO
```

y debe tener acceso activo a:

```text
SEG_USER_APLICA
COD_APLICA = 9
```

De esta forma, deshabilitar un acceso en SGD seguirá teniendo efecto aunque la persona conserve acceso al SSO.

---

## 29. Sesión

El SSO no debería intentar reemplazar la sesión interna del SGD.

La estrategia recomendada es:

```text
SSO autentica
      ↓
SGD recibe identidad
      ↓
SGD crea su propia HttpSession
```

Esto permite conservar:

```text
SessionFilter
usuario
usuarioConfig
sessionId
```

sin modificar el resto del sistema.

---

## 30. Logout

Posteriormente debe definirse qué comportamiento se desea:

### Logout local

Cerrar únicamente la sesión del SGD.

### Logout global

Cerrar:

```text
SGD + SSO + demás sistemas
```

OIDC permite diseñar este comportamiento de forma estándar.

Para una primera integración se puede comenzar con logout local y después agregar Single Logout.

---

## 31. Endpoint recomendado

Una posible estructura sería:

```text
/sgd/sso/login
/sgd/sso/callback
/sgd/sso/logout
```

El callback debe permanecer sin `.do` para no ser bloqueado inicialmente por el `SessionFilter`.

---

## 32. Protección CSRF / state

El inicio de autenticación debería generar un valor aleatorio:

```text
state
```

El callback debe verificar que el `state` recibido sea exactamente el que se generó antes de redirigir al SSO.

Esto impide que un tercero fuerce una autenticación iniciada desde otro contexto.

---

## 33. Protección contra replay

Los tokens o códigos recibidos deben:

- tener tiempo de expiración corto;
- utilizarse una sola vez cuando corresponda;
- validar emisor;
- validar audiencia;
- validar firma;
- validar nonce cuando aplique.

Nunca debe aceptarse únicamente porque el contenido del JWT parezca correcto.

---

## 34. Gestión de claves

El SSO debe firmar los tokens utilizando criptografía asimétrica.

Recomendación:

```text
RS256
```

o un algoritmo moderno equivalente soportado por la plataforma elegida.

Los sistemas consumidores deberían validar con la clave pública del SSO.

No se recomienda compartir una misma clave secreta simétrica entre todos los sistemas.

---

## 35. Compatibilidad con aplicaciones antiguas

Dado que el SGD utiliza:

```text
Java 6 bytecode
Spring 3.2.6
```

es probable que integrar directamente una librería OIDC moderna sea difícil.

Por ello existen dos estrategias.

### Estrategia A — Integración OIDC directamente en el SGD

Ventaja:

- menos componentes.

Desventaja:

- compatibilidad complicada con librerías modernas.

### Estrategia B — Bridge / Gateway SSO

Recomendada para este SGD.

Arquitectura:

```text
SSO moderno
   ↓ OIDC
Bridge / Gateway
   ↓ token interno firmado o sesión segura de corta duración
SGD
```

El bridge puede ejecutarse con Java moderno, Laravel, Node.js, .NET u otra tecnología mantenible.

El pequeño componente agregado al SGD solamente tendría que:

1. recibir una credencial de corta duración;
2. verificar su autenticidad;
3. obtener DNI;
4. crear la sesión local.

Esto reduce el código nuevo dentro de la aplicación legacy.

---

## 36. Recomendación arquitectónica principal

Para este SGD se recomienda:

```text
SSO central
    ↓
OpenID Connect
    ↓
SSO Bridge / Gateway
    ↓
credencial firmada de muy corta duración
    ↓
SGD SsoBridgeController
    ↓
sesión local existente
```

Esto permite mantener el SGD prácticamente intacto.

---

## 37. Nuevo componente propuesto

Podría crearse un JAR independiente, por ejemplo:

```text
sgd-sso-bridge.jar
```

con una clase:

```text
pe.gob.onpe.tramitedoc.web.controller.SsoBridgeController
```

Al estar dentro de:

```text
pe.gob.onpe.tramitedoc
```

será detectado por el `component-scan` existente.

Inicialmente no sería necesario modificar:

- `LoginController`;
- `UsuarioServiceImpl`;
- `UsuarioDaoImpl`;
- `SessionFilter`;
- `MainPanelController`;
- vistas existentes.

El nuevo JAR actuaría únicamente como adaptador.

---

## 38. Cambios mínimos previstos en el SGD

Idealmente:

```text
1 nuevo JAR
+
opcionalmente propiedades de configuración
```

No debería ser necesario recompilar los JAR originales.

Podrían agregarse propiedades como:

```properties
sso.enabled=true
sso.issuer=...
sso.clientId=...
sso.callbackUrl=...
sso.publicKeyUrl=...
```

Los valores definitivos dependerán de la arquitectura elegida.

---

## 39. Aspectos que aún faltan definir

Antes de implementar deben definirse:

1. tecnología definitiva del servidor SSO;
2. protocolo definitivo, preferentemente OIDC;
3. formato y claims de identidad;
4. uso exacto de DNI y `sub`;
5. estrategia para usuarios cuyo `COD_USER` no coincide con DNI;
6. login local de emergencia;
7. comportamiento de logout;
8. expiración de sesión;
9. URLs de desarrollo, pruebas y producción;
10. certificados HTTPS;
11. rotación de claves;
12. auditoría;
13. recuperación ante caída del SSO.

---

## 40. Login local de contingencia

Durante la migración es recomendable mantener temporalmente el login tradicional.

Ejemplo:

```text
/sso/login     → acceso normal
/login.do      → contingencia administrativa
```

El acceso tradicional podría limitarse posteriormente a:

- administradores;
- soporte;
- contingencias del SSO.

No conviene eliminarlo durante la primera etapa de integración.

---

## 41. Auditoría

El SGD ya ejecuta:

```java
usuarioService.registraLogAcceso(usuario, "1");
```

El flujo SSO debería conservar esta llamada.

Adicionalmente, el SSO debería auditar:

```text
usuario
fecha/hora
cliente
aplicación solicitada
resultado
IP
método de autenticación
```

No deben almacenarse tokens completos en logs.

---

## 42. Seguridad de transporte

Toda comunicación SSO debe realizarse mediante:

```text
HTTPS
```

Especialmente:

```text
SSO ↔ navegador
SSO ↔ bridge
bridge ↔ SGD
```

No debe enviarse DNI, token, authorization code o sesión mediante HTTP sin cifrado.

---

## 43. Pruebas mínimas necesarias

Antes de producción deben verificarse al menos:

```text
Usuario válido y con acceso al SGD
Usuario válido pero sin acceso al SGD
Usuario deshabilitado en SGD
Usuario inexistente
Usuario administrador
Usuario sin configuración TDTX_CONFIG_EMP
Usuario con permisos múltiples
Token expirado
Token alterado
Token emitido para otra aplicación
Callback sin state válido
Intento de reutilizar token
Sesión expirada
Logout
Caída temporal del SSO
```

---

## 44. Conclusión

El análisis indica que **no es necesario reescribir la autenticación ni la autorización completa del SGD**.

La estrategia de menor impacto consiste en:

```text
autenticar externamente mediante SSO
+
obtener un DNI confiable
+
reutilizar servicios y DAO existentes
+
crear los mismos objetos usuario y usuarioConfig
+
crear una HttpSession normal
+
continuar usando el SGD sin modificar su funcionamiento interno
```

Los componentes existentes que resultan especialmente reutilizables son:

```text
UsuarioService
UsuarioDao
UsuarioConfigService
SessionFilter
MainPanelController
MenuOpciones
```

La principal adaptación será crear un pequeño **puente SSO** y resolver la validación independiente de `ES_USUARIO`.

Para el SSO nuevo, la recomendación es diseñarlo con **OpenID Connect**, manteniendo al SSO como proveedor de identidad y dejando los permisos funcionales en cada sistema.

---

## 45. Próximo paso recomendado

Cuando se retome la integración:

```text
1. Definir arquitectura y tecnología del SSO.
2. Definir OIDC y claims.
3. Diseñar endpoints del bridge.
4. Crear proyecto sgd-sso-bridge.
5. Implementar primero un mock seguro de identidad.
6. Probar creación de sesión SGD.
7. Integrar finalmente con el SSO real.
```

La prioridad debe ser comprobar primero que el SGD puede abrir correctamente una sesión generada por el nuevo controlador sin modificar el login tradicional.
