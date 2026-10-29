# Fase 1: núcleo SSO MDSJ

## Alcance implementado

| Requisito | Implementación |
|---|---|
| Laravel y PostgreSQL | `sso/`, migraciones y `compose.yaml` |
| Identidad central | DNI único de 8 dígitos, nombres, apellidos, correo opcional, origen, estado, último acceso, `sub` UUID permanente |
| Administración básica | `/admin`: usuarios, clientes, sesiones, últimos 15 eventos de auditoría |
| DNI y contraseña | `/login`, hash bcrypt, limitación por cuenta e IP, cambio obligatorio de contraseña temporal |
| Registro de clientes | Clientes web confidenciales, secreto aleatorio mostrado una sola vez, hash del secreto, varias URL HTTPS exactas |
| Authorization Code | `/authorize`, código aleatorio de 256 bits almacenado como SHA-256, expiración y consumo atómico |
| Tokens | `/token`, ID token y access token separados, firma RS256 mediante `firebase/php-jwt` |
| Claves públicas | `/jwks`, `kid` derivado de la clave RSA; clave privada fuera de `public/` |
| Sesión central | Cookie cifrada, HttpOnly, SameSite=Lax, regeneración de ID, sesiones en PostgreSQL, vencimiento absoluto y por inactividad |

Se agregaron descubrimiento OpenID Connect, `/userinfo`, `nonce`, `at_hash` y soporte PKCE S256 para facilitar y proteger la integración. El proveedor implementa el perfil acotado descrito aquí; no se declara certificación OIDC ni soporte de todas sus extensiones.

## Operación de la consola

- Crear el primer administrador con `php artisan sso:create-admin`, `dev.ps1 admin` o el `AdminSeeder` configurable mediante `SSO_ADMIN_*` (ver README). El seeder exige cambio de contraseña al primer acceso y no modifica cuentas existentes. La consola web no permite otorgar privilegios de administrador.
- Crear usuarios con DNI y contraseña temporal. Deben cambiarla antes de acceder a aplicaciones. Los administradores pueden corregir el DNI sin modificar el `sub`.
- Bloquear usuarios o restablecer contraseñas revoca sus sesiones centrales y códigos pendientes. Activar la cuenta no revive sesiones revocadas.
- Registrar solo sistemas institucionales autorizados: en esta fase todos los usuarios activos pueden autenticarse ante todos los clientes activos. Los permisos de negocio corresponden a cada sistema, tal como indica la especificación.
- Guardar el secreto del cliente en su backend al registrarlo. No se muestra nuevamente ni se almacena en la sesión. Si se pierde, registrar un cliente nuevo y desactivar el anterior; la rotación de credenciales tendrá un flujo posterior.
- No hay modificación ni eliminación de eventos desde la consola. Se registra el usuario afectado y el actor administrador cuando corresponde. La retención, exportación y protección inmutable de auditoría se abordarán en el endurecimiento.

## Flujo de integración

1. Registrar el sistema y su callback, por ejemplo `https://sistema.example/callback`. El dominio es ilustrativo; no se ha supuesto un dominio oficial de la Municipalidad.
2. El backend cliente genera y guarda en su sesión `state` aleatorio de al menos 128 bits, `nonce` y, preferentemente, `code_verifier`. Nunca exponer el secreto en JavaScript.
3. Redirigir el navegador:

   ```text
   GET /authorize?response_type=code
       &client_id=CLIENT_ID
       &redirect_uri=URL_CALLBACK_CODIFICADA
       &scope=openid%20profile%20dni
       &state=VALOR_ALEATORIO_GUARDADO
       &nonce=NONCE_GUARDADO
       &code_challenge=BASE64URL_SHA256_DEL_VERIFIER
       &code_challenge_method=S256
   ```

   La solicitud real debe enviarse en una sola URL. `state` es obligatorio y debe tener entre 16 y 1024 caracteres. PKCE es opcional para estos clientes confidenciales; si se envía el challenge, el canje exige el verifier correspondiente. No se admiten clientes públicos en esta fase.

4. El SSO valida el cliente y la URL antes de iniciar sesión. Conserva la autorización pendiente durante login y cambio de contraseña. Reutiliza la sesión central para otros clientes.
5. El callback recibe `code` y el mismo `state`. **El cliente debe comparar `state` con su sesión y consumirlo una sola vez** antes del canje.
6. El backend canjea el código mediante `POST /token`, con cuerpo `application/x-www-form-urlencoded`:

   ```text
   grant_type=authorization_code
   code=CODIGO_RECIBIDO
   redirect_uri=MISMA_URL_CALLBACK
   code_verifier=VERIFIER_ORIGINAL
   ```

   Autenticar al cliente mediante HTTP Basic (`client_id` y `client_secret`, codificados como exige OAuth) o incluir ambos en el cuerpo. No combinar ambos métodos. No usar query strings para secretos.

7. La respuesta contiene `access_token`, `id_token`, `token_type=Bearer`, `expires_in` y `scope`. El cliente valida la firma mediante JWKS, fija el algoritmo RS256 y comprueba `iss`, `aud`, `exp`, `nonce` y `at_hash` si se utiliza. Vincula por `sub`; usa DNI solo en la migración inicial.
8. `GET /userinfo` acepta exclusivamente `Authorization: Bearer ACCESS_TOKEN`. No acepta ID tokens y verifica además el estado del usuario, cliente y sesión.

Descubrimiento: `/.well-known/openid-configuration`. Scopes: `openid` (obligatorio), `profile` (nombre y apellidos), `dni` y `email`. Los atributos personales solo se entregan si se solicita su scope. No se afirma que el correo esté verificado.

Los errores del canje usan JSON OAuth (`invalid_client`, `invalid_grant`, `invalid_request`, `unsupported_grant_type`). Una URL de retorno inválida nunca recibe una redirección. `prompt=none` devuelve `login_required` o `interaction_required`; `prompt=login` solicita autenticación nueva. No se soportan Request Objects, `claims`, `max_age`, `id_token_hint` ni modos de respuesta alternativos.

## Parámetros y despliegue

| Variable | Valor local inicial |
|---|---|
| `SSO_ISSUER` | `http://127.0.0.1:8085` |
| `SSO_CODE_TTL` | 60 segundos |
| `SSO_TOKEN_TTL` | 300 segundos |
| `SESSION_LIFETIME` | 120 minutos de inactividad |
| `SSO_SESSION_HOURS` | 8 horas absolutas |
| `SSO_LOGIN_ATTEMPTS` | 5 fallos por DNI |
| `SSO_LOCK_SECONDS` | 900 segundos |

También se limita login a 10 solicitudes/minuto/IP y token a 60/minuto/IP. La contraseña nueva requiere 12–72 caracteres, mayúscula, minúscula, número y símbolo. No se usa un servicio externo de consulta de contraseñas; listas institucionales, historial y bloqueo progresivo se reservan para la política definitiva.

Producción requiere `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` y `SSO_ISSUER` con el origen HTTPS real, y `SESSION_SECURE_COOKIE=true`. El middleware rechaza HTTP y configuraciones sin cookies seguras en producción. Publicar exclusivamente `sso/public`, habilitar `pdo_pgsql` en el PHP de producción y restringir el acceso a `.env`, `storage/` y copias de seguridad. Configurar proxies confiables explícitos si TLS termina en un balanceador; no confiar indiscriminadamente en `X-Forwarded-*` del navegador.

Las claves RSA se generan con `sso:keys` (3072 bits) y no se sobrescriben automáticamente. En Windows se deben limitar sus ACL al servicio; en Unix el generador usa permisos 0600. Respaldar `APP_KEY`, claves RSA y PostgreSQL mediante el procedimiento institucional. `compose.yaml` y el servidor PHP de `dev.ps1` están destinados a desarrollo local. El dominio, TLS, monitoreo, respaldos y despliegue público deben definirse para la infraestructura real.

Referencias del diseño: [Laravel: autenticación](https://laravel.com/docs/13.x/authentication), [OpenID Connect Core](https://openid.net/specs/openid-connect-core-1_0.html).

## Límites de esta entrega

La fase 2 incorpora un sistema municipal real. Certificados, DNIe, importación SGD/RR. HH., Client Credentials, recuperación por correo, logout global entre aplicaciones, rotación de claves y auditoría avanzada siguen las fases posteriores del documento original. `/logout` es POST con CSRF y cierra la sesión central; no anuncia un endpoint RP-Initiated Logout.

Revocar la sesión o bloquear una cuenta impide nuevas autorizaciones y el uso de `/userinfo`. Un ID token ya entregado no desaparece de los clientes y las sesiones locales deben obedecer sus propias políticas hasta implementar logout global. No se entregan refresh tokens.
