# Diseño de vistas del SSO

Las vistas Blade conservan los formularios, rutas, permisos y sistemas del SSO. El diseño sigue la referencia: fondo papel, azul, turquesa, amarillo, tinta oscura, accesos con relieve y barra de identidad. Solo se modificaron vistas y CSS; no hay dependencias nuevas del proyecto.

## Vistas

- Portal: saludo según la hora de Lima, sistemas reales y barra de identidad. Los accesos se distribuyen en varias filas cuando es necesario. Solo aparece Administrar para administradores.
- Inicio de sesión: formulario original con ilustración SVG de una identidad que conecta varios sistemas.
- Contraseña: formulario original con icono de seguridad y regreso al escritorio cuando no se requiere el cambio inicial.
- Administración: navegación con sección activa, tarjetas de estadísticas y tablas con desplazamiento horizontal.

## Componentes

En `sso/resources/views/components/`:

- `brand-mark`: sello SJ dibujado con trazados SVG.
- `icon`: iconos inline con `currentColor`. Nombres: `document`, `inbox`, `users`, `building`, `key`, `logout`, `grid`, `shield`, `clock`, `arrow`, `apps`.
- `access-illustration`: ilustración SVG para el login.
- `system-tile`: acceso a un sistema. El código y nombre determinan su icono; los sistemas desconocidos reciben un icono genérico. La descripción aparece al enfocar o pasar el cursor.
- `identity-bar`: avatar con iniciales, datos de cuenta y acciones.

Ejemplo: `<x-icon name="document" />`. Los colores, tamaños y puntos de adaptación están en `sso/public/css/sso.css`; se sirve directamente sin compilar con npm.

## Capturas

Capturas de las vistas renderizadas con datos de ejemplo; no representan sistemas o cuentas agregados a la base de datos.

- [Portal en escritorio](diseno-sso/portal-1600.png)
- [Portal en móvil](diseno-sso/portal-390.png)
- [Inicio de sesión](diseno-sso/login-1600.png)
- [Administración](diseno-sso/admin-1600.png)

## Validación

- 33 pruebas existentes correctas, con 244 aserciones. En Windows se ejecutaron con `OPENSSL_CONF=C:/php8.4/extras/ssl/openssl.cnf` configurado para ese proceso.
- Chrome: portal, estado vacío, login, contraseña y resumen administrativo a 320, 390, 768 y 1600 px, sin desbordamiento de página.
- Comprobados el acceso a descripciones por teclado y el respeto a la preferencia de movimiento reducido.
- Foco visible, enlace para saltar al contenido, SVG decorativos excluidos del árbol de accesibilidad y formularios con etiquetas.
