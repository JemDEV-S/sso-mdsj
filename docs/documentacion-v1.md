# Diseño y Especificación Técnica Inicial del Sistema de Identidad y Autenticación Centralizada (SSO)

**Versión:** 0.1  
**Estado:** Documento inicial para diseño y desarrollo

---

## 1. Introducción

La Municipalidad requiere centralizar la autenticación de los diferentes sistemas informáticos institucionales, los cuales actualmente mantienen mecanismos independientes de acceso y bases de datos de usuarios separadas.

Esta situación genera duplicidad de credenciales, dificultad para controlar altas y bajas de usuarios, inconsistencias en los datos y mayor esfuerzo para administrar el acceso a los sistemas.

Para resolver esta necesidad se implementará una plataforma centralizada de identidad y autenticación, en adelante **SSO**, que permitirá autenticar a los usuarios desde un único servicio y reutilizar dicha autenticación en los sistemas integrados.

El sistema será diseñado inicialmente para aplicaciones web institucionales, manteniendo la posibilidad de incorporar nuevos sistemas en el futuro.

---

## 2. Objetivo

Implementar un servicio centralizado de identidad y autenticación que permita:

- autenticar usuarios desde un único punto;
- utilizar una misma identidad para acceder a diferentes sistemas;
- reducir la administración independiente de credenciales;
- centralizar el bloqueo y habilitación de usuarios;
- soportar diferentes mecanismos de autenticación;
- mantener compatibilidad con sistemas desarrollados en distintas tecnologías;
- facilitar la incorporación progresiva de nuevos sistemas;
- mejorar la seguridad y trazabilidad de los accesos.

---

## 3. Alcance

El SSO comprenderá:

- servidor central de autenticación;
- administración de usuarios;
- autenticación mediante DNI y contraseña;
- autenticación mediante certificado digital instalado en el equipo;
- autenticación mediante certificado digital contenido en DNI electrónico;
- gestión centralizada de sesiones;
- cambio y recuperación de contraseña;
- bloqueo y desbloqueo de usuarios;
- registro de eventos de autenticación;
- administración de sistemas clientes;
- integración mediante protocolos estándar;
- integración con sistemas desarrollados en Laravel, Java, PHP y otras tecnologías compatibles;
- soporte para autenticación máquina a máquina;
- mecanismos para futuras integraciones con los sistemas institucionales de personal.

La cantidad de sistemas clientes no será fija. La solución deberá permitir incorporar progresivamente nuevos sistemas.

En una primera etapa estarán considerados principalmente sistemas propios de la Municipalidad. La integración con sistemas externos deberá ser evaluada individualmente.

---

## 4. Principios de diseño

La solución deberá respetar los siguientes principios:

1. **Uso de estándares.**  
   La autenticación se implementará siguiendo OAuth 2.0 y OpenID Connect, principalmente mediante el flujo Authorization Code.

2. **Identidad centralizada.**  
   El SSO será responsable de determinar la identidad autenticada del usuario.

3. **DNI como identificador institucional.**  
   El DNI será utilizado como principal dato para relacionar una persona entre diferentes sistemas.

4. **Identificador interno permanente.**  
   Además del DNI, el SSO mantendrá un identificador interno único e inmutable (`sub`) para evitar dependencia directa de datos que podrían requerir corrección o actualización.

5. **Separación entre autenticación y autorización.**  
   El SSO determinará quién es el usuario. Los roles y permisos específicos continuarán inicialmente siendo administrados dentro de cada sistema.

6. **Múltiples métodos de autenticación.**  
   El diseño permitirá utilizar contraseña, certificados digitales y DNIe sin modificar la lógica de los sistemas clientes.

7. **Mínimo acoplamiento.**  
   Los sistemas deberán requerir únicamente los cambios necesarios para delegar la autenticación al SSO y crear posteriormente su propia sesión local.

8. **Seguridad por defecto.**  
   Las comunicaciones utilizarán HTTPS, tokens de corta duración, validaciones criptográficas y controles contra ataques comunes.

---

## 5. Arquitectura general

La arquitectura estará compuesta por:

### 5.1 Servidor SSO

Será desarrollado en **Laravel** y utilizará **PostgreSQL** como motor de base de datos.

Será responsable de:

- autenticación de usuarios;
- administración de identidades;
- validación de certificados digitales;
- emisión de códigos de autorización;
- emisión y validación de tokens;
- administración de sesiones centrales;
- registro de clientes;
- control de accesos fallidos;
- recuperación de contraseñas;
- auditoría;
- cierre centralizado de sesiones.

### 5.2 Sistemas clientes

Cada sistema integrado delegará el proceso de autenticación al SSO.

Los sistemas clientes podrán continuar utilizando sus propias sesiones internas una vez autenticado el usuario.

El SSO entregará un identificador estable del usuario, que permitirá relacionarlo con la cuenta existente en cada aplicación.

### 5.3 Bases de datos existentes

Cada sistema continuará inicialmente utilizando su propia base de datos.

Las tablas de usuarios existentes no serán reemplazadas inmediatamente. Se incorporará progresivamente una relación entre el usuario local y su identidad central.

---

## 6. Identidad del usuario

El registro central deberá manejar, como mínimo:

- identificador interno;
- DNI;
- nombres;
- apellidos;
- correo electrónico;
- estado de la cuenta;
- contraseña cifrada;
- origen del registro;
- fecha de creación;
- fecha de actualización;
- fecha del último acceso.

El identificador publicado hacia los sistemas será un valor interno denominado `sub`.

El DNI tendrá una restricción de unicidad para personas naturales registradas dentro del ámbito institucional.

El correo electrónico no será utilizado como identificador principal, debido a que no todos los sistemas existentes manejan necesariamente el mismo correo o estructura de datos.

---

## 7. Creación y actualización de usuarios

El sistema deberá permitir diferentes mecanismos de creación de usuarios.

### 7.1 Registro manual

Usuarios autorizados podrán crear cuentas directamente desde la consola administrativa.

### 7.2 Importación de información institucional

Se deberá permitir importar usuarios desde una fuente institucional existente.

Inicialmente podrá considerarse el sistema SGD, que actualmente contiene información de trabajadores y estructura organizacional.

### 7.3 Integración futura con Recursos Humanos

El sistema de Recursos Humanos que se encuentra en desarrollo deberá evaluarse como futura fuente principal de información de trabajadores.

Cuando dicho sistema alcance un nivel adecuado de madurez, podrá convertirse en la fuente de verdad para:

- altas de trabajadores;
- actualización de datos;
- dependencia organizacional;
- estado laboral;
- ceses;
- reincorporaciones.

La integración podrá realizarse mediante API, procesos programados u otro mecanismo que se defina posteriormente.

### 7.4 Origen del usuario

Cada cuenta deberá registrar su origen, por ejemplo:

- MANUAL;
- SGD;
- RECURSOS_HUMANOS;
- IMPORTACION;
- OTRO.

Esto permitirá mantener trazabilidad y facilitar futuras migraciones.

---

## 8. Métodos de autenticación

El SSO deberá estar preparado para soportar diferentes métodos de autenticación.

### 8.1 DNI y contraseña

Será el método convencional de acceso.

El usuario ingresará:

- DNI;
- contraseña.

La contraseña será almacenada exclusivamente como hash seguro.

### 8.2 Certificado digital instalado

El sistema deberá permitir autenticar al usuario utilizando certificados digitales instalados en el equipo del trabajador.

La implementación deberá:

- solicitar un certificado válido;
- verificar la cadena de confianza;
- verificar su vigencia;
- comprobar que no haya sido revocado cuando sea técnicamente posible;
- obtener los datos necesarios para identificar al titular;
- relacionar el certificado con el DNI registrado en el SSO.

Los emisores de certificados admitidos deberán configurarse mediante una lista de autoridades de confianza.

### 8.3 DNI electrónico

También deberá permitirse el acceso mediante certificado digital contenido en el DNI electrónico.

El mecanismo de autenticación deberá integrarse con los componentes necesarios para utilizar el certificado del DNIe y validar criptográficamente la identidad del titular.

### 8.4 Independencia del método utilizado

Los sistemas clientes no deberán conocer cómo fue autenticado el usuario.

Para ellos el resultado será únicamente una identidad validada por el SSO.

Podrá incluirse en el token un atributo que indique el método de autenticación utilizado.

---

## 9. Gestión de contraseñas

El sistema permitirá:

- cambio voluntario de contraseña;
- recuperación de contraseña;
- restablecimiento administrativo;
- contraseña temporal;
- obligación de cambio de contraseña en el siguiente acceso.

Todo usuario creado con contraseña temporal deberá modificarla durante su primer inicio de sesión.

La política específica será configurable y deberá contemplar como mínimo:

- longitud mínima;
- complejidad;
- protección contra contraseñas triviales;
- límite de intentos fallidos;
- bloqueo temporal;
- prevención de reutilización cuando corresponda.

---

## 10. Flujo de autenticación SSO

El flujo principal será:

1. El usuario accede a un sistema.
2. El sistema verifica si tiene una sesión local.
3. Si no existe sesión, genera un valor `state`.
4. El sistema redirige al navegador hacia el SSO.
5. El SSO valida el sistema solicitante.
6. Si no existe una sesión SSO activa, solicita autenticación.
7. El usuario utiliza uno de los mecanismos habilitados.
8. El SSO valida la identidad.
9. El SSO genera un código de autorización de un solo uso.
10. El navegador regresa al sistema cliente.
11. El cliente valida `state`.
12. El backend del cliente canjea el código por un token.
13. El cliente valida criptográficamente el token.
14. El sistema relaciona el `sub` con el usuario local.
15. Se crea la sesión local del sistema.

Cuando el usuario ingrese posteriormente a otro sistema integrado, el SSO reutilizará su sesión central y no solicitará nuevamente las credenciales mientras dicha sesión continúe vigente.

---

## 11. Vinculación con usuarios existentes

Debido a que actualmente los sistemas mantienen bases independientes, la integración deberá contemplar un proceso de vinculación.

Se añadirá en cada sistema un campo equivalente a:

` sso_id `

Este valor almacenará el `sub` entregado por el SSO.

Durante la migración inicial podrá utilizarse el DNI para localizar el usuario correspondiente.

Una vez realizada la vinculación, las autenticaciones posteriores utilizarán el `sso_id` y no dependerán nuevamente de coincidencias por DNI.

Los casos de:

- DNI duplicado;
- DNI inexistente;
- usuario sin DNI;
- información contradictoria;
- cuentas duplicadas;

deberán pasar por un procedimiento de regularización.

---

## 12. Sesiones

Existirán dos tipos de sesión:

### Sesión central

Es mantenida por el servidor SSO.

Permite que un usuario autenticado pueda acceder a otros sistemas sin volver a ingresar sus credenciales.

### Sesión local

Es administrada por cada sistema cliente.

Permite conservar compatibilidad con la lógica interna de las aplicaciones existentes.

La duración máxima de las sesiones deberá ser configurable.

También deberá evaluarse la posibilidad de configurar:

- tiempo máximo absoluto;
- expiración por inactividad;
- renovación controlada;
- cierre desde la consola administrativa.

---

## 13. Cierre de sesión

Se contemplarán:

### 13.1 Cierre local

Finaliza únicamente la sesión del sistema actual.

### 13.2 Cierre global

Finaliza la sesión central y deberá invalidar o notificar a los sistemas integrados para cerrar las sesiones correspondientes.

### 13.3 Cierre administrativo

Un administrador autorizado podrá finalizar:

- una sesión determinada;
- todas las sesiones de un usuario;
- todas las sesiones asociadas a un sistema cliente.

Cuando un usuario sea bloqueado, el SSO deberá impedir nuevas autenticaciones y podrá ordenar el cierre de las sesiones existentes.

---

## 14. Roles y permisos

Inicialmente los roles y permisos continuarán siendo administrados por cada sistema.

Esto se debe a que actualmente las estructuras de autorización se encuentran dispersas y no están normalizadas.

El SSO no deberá intentar reemplazarlas durante la primera etapa.

Sin embargo, el modelo deberá estar preparado para soportar en el futuro atributos globales, grupos o perfiles institucionales.

Un usuario podrá disponer de múltiples roles dentro de un mismo sistema.

La relación conceptual será:

**Usuario → Sistema → uno o más roles → permisos**

El SSO podrá registrar qué sistemas están asociados a un usuario, pero el sistema cliente continuará siendo responsable de aplicar sus permisos.

---

## 15. Administración de sistemas clientes

La consola administrativa deberá permitir registrar sistemas.

Cada sistema deberá disponer de:

- nombre;
- código;
- `client_id`;
- credencial del cliente;
- una o más URL de redirección autorizadas;
- URL de cierre de sesión;
- estado;
- fecha de creación;
- configuración adicional de seguridad.

Las URL de redirección deberán coincidir exactamente con las registradas.

La solución deberá soportar dominios distintos, ya que los sistemas institucionales pueden encontrarse alojados en proveedores externos.

---

## 16. Interfaces principales

La implementación deberá contemplar, como mínimo:

| Endpoint | Función |
|---|---|
| `/authorize` | Iniciar autorización |
| `/login` | Autenticación convencional |
| `/token` | Canje de código por token |
| `/logout` | Cierre de sesión |
| `/jwks` | Publicación de claves públicas |
| `/.well-known/openid-configuration` | Descubrimiento de configuración |
| `/userinfo` | Consulta de información básica del usuario |

Los nombres definitivos podrán ajustarse durante la implementación, manteniendo compatibilidad con OpenID Connect.

---

## 17. Tokens

Los tokens serán firmados criptográficamente mediante algoritmo asimétrico.

Se utilizará preferentemente RSA con SHA-256 o un algoritmo estándar equivalente soportado por los clientes.

Un token de identidad podrá contener:

```json
{
  "iss": "https://sso.municipalidad.gob.pe",
  "aud": "sistema-cliente",
  "sub": "identificador-interno",
  "dni": "00000000",
  "name": "Nombre Apellido",
  "auth_method": "password",
  "iat": 1700000000,
  "exp": 1700000300
}
```

Todo sistema deberá validar:

- firma;
- emisor;
- audiencia;
- expiración;
- algoritmo permitido.

Los tokens tendrán una vida útil corta.

---

## 18. Autenticación máquina a máquina

La arquitectura deberá permitir autenticación entre aplicaciones sin intervención de un usuario.

Este mecanismo será utilizado únicamente para servicios institucionales previamente registrados.

Podrá implementarse utilizando el flujo OAuth 2.0 Client Credentials.

Cada servicio deberá contar con credenciales independientes.

Los permisos otorgados a una aplicación deberán ser mínimos y específicos.

---

## 19. Consola administrativa

El SSO deberá disponer de una interfaz administrativa para usuarios autorizados.

Permitirá:

- administrar usuarios;
- activar y bloquear cuentas;
- restablecer contraseñas;
- exigir cambio de contraseña;
- revisar sesiones activas;
- cerrar sesiones;
- registrar y administrar sistemas clientes;
- administrar URL de redirección;
- consultar historial de accesos;
- consultar intentos fallidos;
- revisar eventos de seguridad;
- visualizar información básica de auditoría;
- gestionar parámetros generales.

---

## 20. Auditoría

El sistema deberá registrar eventos relevantes.

Como mínimo:

- autenticación exitosa;
- autenticación fallida;
- método de autenticación utilizado;
- cambio de contraseña;
- recuperación de contraseña;
- bloqueo y desbloqueo;
- inicio y cierre de sesión;
- creación o modificación de usuarios;
- cambios en clientes;
- cierre administrativo de sesiones.

Cuando corresponda también se almacenará:

- fecha y hora;
- usuario;
- dirección IP;
- navegador;
- sistema cliente;
- resultado de la operación.

Los registros de auditoría no deberán poder ser modificados desde la interfaz administrativa ordinaria.

---

## 21. Seguridad

La solución deberá cumplir, como mínimo, las siguientes condiciones:

- HTTPS obligatorio;
- TLS actualizado;
- validación estricta de `redirect_uri`;
- protección mediante `state`;
- uso de PKCE cuando corresponda;
- códigos de autorización de un solo uso;
- expiración corta de códigos;
- tokens de duración limitada;
- protección contra reutilización de códigos;
- hashing seguro de contraseñas;
- hashing o cifrado seguro de secretos;
- rate limiting;
- bloqueo progresivo ante intentos fallidos;
- regeneración de identificadores de sesión;
- cookies `HttpOnly`;
- cookies `Secure`;
- configuración adecuada de `SameSite`;
- protección CSRF;
- protección XSS;
- validación estricta de datos;
- rotación de claves criptográficas;
- administración segura de secretos;
- auditoría de accesos administrativos.

Las claves privadas no deberán almacenarse dentro de repositorios de código ni en ubicaciones accesibles públicamente.

---

## 22. Disponibilidad

Debido a que el SSO se convertirá en un servicio crítico, una indisponibilidad podría impedir nuevos accesos a múltiples sistemas.

La infraestructura deberá contemplar:

- monitoreo;
- copias de seguridad;
- recuperación ante fallos;
- procedimientos de restauración;
- registro de errores;
- disponibilidad adecuada del servidor y base de datos.

Los sistemas que ya cuenten con una sesión local válida podrán continuar funcionando según las políticas que se definan, incluso si el SSO presenta una indisponibilidad temporal.

---

## 23. Compatibilidad tecnológica

### Laravel

Se implementará mediante middleware y controladores compatibles con OAuth/OIDC.

### Java

Podrá integrarse mediante Spring Security, servlets u otros mecanismos disponibles según la versión del sistema.

### PHP

Se utilizarán librerías estándar para OAuth, OpenID Connect y validación JWT.

La integración no deberá depender exclusivamente de una tecnología concreta.

Cualquier aplicación web que implemente correctamente el protocolo podrá incorporarse posteriormente.

---

## 24. Consideraciones de infraestructura

La mayoría de los sistemas se encuentran expuestos a Internet y pueden estar alojados en diferentes servicios de hosting.

Por ello:

- no se asumirá que los sistemas comparten dominio;
- no se compartirán cookies entre aplicaciones;
- la comunicación entre sistemas y SSO deberá utilizar HTTPS;
- las integraciones servidor a servidor deberán realizarse mediante endpoints protegidos;
- los dominios autorizados deberán registrarse expresamente.

---

## 25. Modelo de datos inicial

### users

- id;
- dni;
- nombres;
- apellidos;
- email;
- password_hash;
- active;
- must_change_password;
- source;
- last_login_at;
- created_at;
- updated_at.

### clients

- id;
- client_id;
- client_secret_hash;
- name;
- active;
- created_at;
- updated_at.

### client_redirect_uris

- id;
- client_id;
- redirect_uri.

### auth_codes

- id;
- code_hash;
- user_id;
- client_id;
- expires_at;
- used_at.

### user_sessions

- id;
- user_id;
- session_identifier;
- ip_address;
- user_agent;
- authenticated_with;
- last_activity_at;
- expires_at;
- revoked_at.

### audit_logs

- id;
- user_id;
- event;
- client_id;
- ip_address;
- user_agent;
- metadata;
- created_at.

### user_certificates

- id;
- user_id;
- certificate_identifier;
- issuer;
- serial_number;
- valid_from;
- valid_until;
- active.

El modelo podrá ampliarse durante el desarrollo.

---

## 26. Implementación por fases

### Fase 1 — Núcleo

- proyecto Laravel;
- PostgreSQL;
- modelo de usuarios;
- consola administrativa básica;
- autenticación DNI y contraseña;
- registro de clientes;
- `/authorize`;
- `/token`;
- JWT;
- JWKS;
- sesiones centrales.

### Fase 2 — Primer sistema piloto

Integración de un sistema institucional y validación completa del flujo SSO.

### Fase 3 — Certificados digitales

- certificados instalados;
- identificación del titular;
- validación criptográfica;
- integración con DNIe.

### Fase 4 — Integración progresiva

Incorporación de los sistemas Java, Laravel y PHP existentes.

### Fase 5 — Gestión institucional de usuarios

- importaciones;
- integración con SGD cuando corresponda;
- definición e integración futura con Recursos Humanos.

### Fase 6 — Endurecimiento

- logout global;
- PKCE;
- rotación de claves;
- auditoría avanzada;
- monitoreo;
- pruebas de seguridad.

### Fase 7 — Servicios máquina a máquina

Implementación de Client Credentials y APIs institucionales.

---

## 27. Funcionalidades futuras

La arquitectura deberá permitir incorporar posteriormente:

- autenticación multifactor;
- aplicaciones móviles;
- proveedores externos de identidad;
- integración con Active Directory o LDAP;
- centralización progresiva de grupos y perfiles;
- aprovisionamiento automático;
- políticas de acceso por contexto;
- firma digital;
- servicios institucionales adicionales de identidad.

---

## 28. Decisiones pendientes

Durante el desarrollo deberán definirse específicamente:

- mecanismo técnico definitivo para autenticación mediante certificado instalado;
- política detallada de contraseñas;
- duración predeterminada de sesiones;
- procedimiento institucional para recuperación de cuentas;
- proceso definitivo de altas y bajas de trabajadores;
- integración con SGD;
- integración con el sistema de Recursos Humanos;
- estrategia futura de roles y permisos;
- infraestructura definitiva de producción;
- política de respaldo y alta disponibilidad.

Estas decisiones no afectan la arquitectura general y podrán cerrarse progresivamente durante la implementación.

---

## 29. Resultado esperado

Al finalizar la implementación, la Municipalidad dispondrá de una plataforma central de identidad que permitirá que un trabajador se autentique una sola vez y pueda acceder a los sistemas institucionales habilitados utilizando una identidad común.

La plataforma permitirá utilizar diferentes mecanismos de autenticación sin requerir que cada sistema implemente individualmente la gestión de credenciales o certificados.

Los sistemas conservarán inicialmente su lógica propia de roles y permisos, mientras que la identidad, autenticación, sesiones principales y trazabilidad serán gestionadas desde un servicio centralizado.