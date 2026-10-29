# SSO · Municipalidad Distrital de San Jerónimo

Implementación de la **fase 1 — Núcleo** de [la especificación](docs/documentacion-v1.md). La aplicación se encuentra en `sso/`: Laravel 13, PHP 8.3 o superior, PostgreSQL y vistas Blade en español. La interfaz no requiere Node ni compilación de recursos.

Incluye acceso por DNI, usuarios con `sub` UUID permanente, administración de usuarios y sistemas clientes, sesiones centrales, códigos de autorización de un solo uso, ID tokens y access tokens JWT RS256, JWKS, descubrimiento y UserInfo. La consola también permite bloquear cuentas, asignar contraseñas temporales y revocar sesiones centrales.

## Inicio local (Windows / PowerShell)

Requisitos: PHP con `openssl`, `pdo_pgsql`, `mbstring`, `xml`, `curl` y las extensiones habituales de Laravel; Composer y Docker Desktop iniciado. El script `dev.ps1` habilita `pdo_pgsql` en cada proceso si la DLL está instalada y detecta `openssl.cnf` junto a PHP, sin modificar el `php.ini` del equipo.

1. Preparar la aplicación, si todavía no se ha instalado:

   ```powershell
   cd sso
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   cd ..
   ```

   En `sso/.env`, establecer un `DB_PASSWORD` aleatorio. No sobrescribir un `.env` ya configurado. Las credenciales y claves privadas están excluidas del repositorio.

2. Iniciar PostgreSQL desde la raíz:

   ```powershell
   docker compose --env-file sso/.env up -d postgres
   ```

   PostgreSQL se publica únicamente en `127.0.0.1:55432`; se eligió este puerto para evitar el servicio que ya utiliza 5432. `DB_PORT` permite ajustarlo.

3. Preparar las tablas, las claves y la cuenta administradora:

   ```powershell
   cd sso
   .\dev.ps1 migrate
   .\dev.ps1 keys
   .\dev.ps1 admin
   ```

   `keys` no sobrescribe claves existentes. `admin` solicita DNI, nombres y una contraseña oculta, sin cuentas predeterminadas. En este workspace ya se generaron las claves y se ejecutaron las migraciones; queda crear el administrador con sus datos reales.

4. Iniciar la aplicación:

   ```powershell
   .\dev.ps1 serve
   ```

   Abrir **http://127.0.0.1:8085**. Mantener ese mismo hostname en `APP_URL` y `SSO_ISSUER`. La consola está en `/admin`.

En Linux o en PHP con PostgreSQL habilitado globalmente, los equivalentes son `php artisan migrate`, `php artisan sso:keys`, `php artisan sso:create-admin` y `php artisan serve`.

## Administrador mediante seeder

Como alternativa al comando interactivo, completar en `sso/.env` las variables `SSO_ADMIN_DNI`, `SSO_ADMIN_NOMBRES`, `SSO_ADMIN_APELLIDOS`, `SSO_ADMIN_EMAIL` (opcional) y `SSO_ADMIN_PASSWORD`. El DNI debe tener 8 dígitos; la contraseña temporal debe tener 12–72 caracteres, mayúscula, minúscula, número y símbolo. Colocar valores con espacios o caracteres especiales entre comillas.

Desde `sso/`, ejecutar:

```powershell
php artisan config:clear
php -d extension=pdo_pgsql artisan db:seed --class=AdminSeeder
```

También se ejecuta con `db:seed` mediante `DatabaseSeeder`. Si PHP ya carga `pdo_pgsql`, omitir `-d extension=pdo_pgsql`. El administrador deberá cambiar su contraseña al ingresar. Ejecutarlo nuevamente no modifica ni reactiva cuentas existentes; rechaza un DNI perteneciente a un usuario no administrador. Después de crear la cuenta, retirar `SSO_ADMIN_PASSWORD` del `.env` y limpiar la caché de configuración.

## Pruebas

```powershell
cd sso
.\dev.ps1 test
```

La suite predeterminada usa SQLite en memoria. Para ejecutarla con PostgreSQL, crear una base exclusiva para pruebas desde la raíz del proyecto:

```powershell
docker compose --env-file sso/.env exec -T postgres createdb -U sso_mdsj sso_mdsj_test
cd sso
.\dev.ps1 test-pg
```

`phpunit.pgsql.xml` fija el nombre `sso_mdsj_test`; las pruebas reconstruyen las tablas de esa base. Nunca apuntar esta configuración a una base con datos reales. Ambos recorridos verifican login, JWT/JWKS, canje y reutilización de códigos, clientes, scopes, bloqueos, CSRF y expiración/revocación de sesiones.

## Integración y operación

Consultar [la guía de fase 1](docs/fase-1.md) para registrar clientes, probar Authorization Code, configurar producción y conocer los límites implementados.
