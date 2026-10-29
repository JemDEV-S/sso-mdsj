# Concepto de diseño — SSO MDSJ

> Documento para pasar a **Claude Diseño**. Resume qué es el sistema, qué se necesita y la dirección
> visual deseada (amigable, creativa, intuitiva y minimalista) para rediseñar dos vistas:
> el **portal del usuario** y el **panel de administración**. Cada uno con su propia personalidad.

---

## 1. Qué es el producto

El **SSO MDSJ** (Single Sign-On de la Municipalidad Distrital de San Jerónimo) es la **puerta de
entrada única** a todos los sistemas institucionales. El funcionario inicia sesión una sola vez con
su **DNI + contraseña institucional** y desde ahí entra a cualquier sistema (el primero integrado es
el **SGD** — Sistema de Gestión Documental) **sin volver a autenticarse**.

La metáfora central es: **"Tu identidad. Todos tus sistemas."** Una sola llave que abre varias puertas.

Hay **dos tipos de usuario** y por eso **dos experiencias distintas**:

| Perfil | Qué hace | Tono del diseño |
|---|---|---|
| **Usuario normal** (funcionario) | Inicia sesión y lanza los sistemas a los que tiene acceso. | Cálido, acogedor, tipo "escritorio personal". Pocos elementos, mucho aire. |
| **Administrador** | Gestiona usuarios, sistemas/clientes, sesiones activas. Trabajo denso de datos. | Sobrio, eficiente, orientado a tablas y control. Más "consola" que "escritorio". |

---

## 2. Identidad institucional (elementos representativos)

Estos son los anclajes de marca que **deben aparecer** para que se sienta "de la Municipalidad":

- **Monograma "SJ"** — San Jerónimo. Hoy es un recuadro con esquinas inferiores redondeadas
  (forma de escudo/sello, `border-radius: 8px 8px 20px 20px`), tipografía serif. Mantener esa idea
  de **sello / placa institucional**, se puede reinterpretar en SVG.
- **Nombre completo:** "Municipalidad Distrital de San Jerónimo".
- **Etiqueta de servicio:** "IDENTIDAD INSTITUCIONAL" / "Plataforma de acceso institucional · SSO".
- **Paleta actual** (conservar como base, se puede enriquecer con degradados suaves):
  - Verde institucional `#146b50` (color primario, acción, marca)
  - Verde tinta `#17372f` (texto principal)
  - Dorado `#bb8b3c` (acento, foco, detalles ceremoniales)
  - Papel `#f4f6f2` (fondo cálido, no blanco puro)
  - Apoyo: `#61736d` (texto secundario), `#dde5df` (líneas)
- **Tipografía:** serif (Georgia) para titulares — da peso institucional/formal;
  sans-serif del sistema para cuerpo y datos.

> **Sugerencia creativa:** el verde + dorado + sello remiten a lo cívico/andino de Cusco
> (San Jerónimo es un distrito de Cusco). Se pueden incorporar **motivos geométricos sutiles tipo
> greca / textil andino** como textura de fondo o separadores en SVG — discretos, nunca recargados.

---

## 3. Imágenes y SVG (qué ilustrar)

Todo decorativo debe ser **SVG inline, ligero, de línea fina**, en los colores de marca. Ideas:

- **Escudo / sello "SJ"** reinterpretado como SVG (reemplaza el monograma de CSS).
- **Metáfora de "una llave, varias puertas"**: una llave que se conecta a varios iconos de sistema.
- **Iconos por sistema**: cada tarjeta de sistema con su propio glyph SVG (documento para el SGD, etc.),
  no solo la inicial del nombre.
- **Grecas / patrón geométrico andino** como marca de agua muy tenue en el fondo del portal.
- **Ilustración de bienvenida** en el login (lado izquierdo): algo cálido y abstracto, no fotográfico.

Evitar: fotos de stock, sombras pesadas, gradientes chillones, exceso de iconografía.

---

## 4. Vista 1 — Portal del usuario (la principal, "como un escritorio")

Esta es la vista que el usuario ve tras iniciar sesión. **Referencia pedida: que se sienta como un
escritorio muy amigable** (tipo pantalla de inicio / launcher personal), pero **único y novedoso**,
no una copia del escritorio de Windows.

### Qué contiene hoy (y debe seguir existiendo)
- Saludo personal: "Hola, {nombres}" + confirmación de que la sesión central está activa.
- **"Tus sistemas"**: grilla de **tarjetas lanzadoras** (hoy `.system-card`). Cada tarjeta = un sistema
  al que el usuario tiene acceso; al hacer clic, abre ese sistema ya logueado (`launch_url`).
  Tiene: icono, nombre, descripción, y un "Ingresar →".
- Estado vacío amable: "No tienes sistemas disponibles por ahora."
- **"Datos de tu cuenta"**: nombre completo, DNI, correo; botón "Cambiar contraseña"; y si es admin,
  botón "Administrar SSO".

### Dirección de diseño para el portal
- **Metáfora de escritorio personal / lanzadera**: las tarjetas de sistema son los protagonistas,
  grandes y táctiles, como "apps" de un launcher. Pensar en un **tablero/dashboard personal** más que
  en una página web con lista.
- **Lo novedoso y único** (elegir 1–2, no todo):
  - Un **encabezado contextual** que cambia con la hora ("Buenos días/tardes") y un saludo cálido.
  - Las tarjetas con **profundidad sutil al pasar el cursor** (ya hay `translateY(-3px)`), pudiendo
    sumar un brillo dorado tenue o el icono que "cobra vida".
  - Un **arco/greca decorativo** de fondo que enmarca el espacio como un "escritorio".
  - La sección "Datos de tu cuenta" como una **tarjeta de identidad / carné** discreto (refuerza
    la idea de "tu identidad"), no como un formulario.
- **Minimalista**: máximo 2 zonas visibles (los sistemas, y mi identidad). Mucho aire. Jerarquía clara.
- **Cálido y amigable**: lenguaje en primera persona, bordes redondeados, fondo papel, nada frío.
- **Accesible**: foco visible (dorado), contraste AA, objetivos táctiles grandes, responsive
  (hoy la grilla es `auto-fill minmax(260px,1fr)` y colapsa bien en móvil).

---

## 5. Vista 2 — Panel de administración (debe ser DIFERENTE)

El admin NO debe sentirse como el escritorio del usuario. Es una **consola de gestión**: más densa,
más sobria, orientada a productividad y a datos. Mismo ADN de marca (colores, sello, serif en títulos)
pero con tratamiento distinto.

### Qué contiene hoy
- **Resumen** (`admin/index`): tarjetas de estadísticas (números grandes) — usuarios, sistemas, sesiones.
- **Usuarios** (`admin/users`): tabla + búsqueda, formularios de alta/edición, badges de estado
  (activo/bloqueado), paginación.
- **Sistemas/Clientes** (`admin/clients`): tabla de sistemas integrados (OIDC clients), formularios,
  pantalla de "client secret".
- **Sesiones** (`admin/sessions`): tabla de sesiones activas.

### Dirección de diseño para el admin
- **Layout de consola**: navegación lateral o superior persistente, área de contenido ancha y densa.
  Priorizar **legibilidad de tablas** (hoy ya hay `th` en mayúsculas, badges, `table-wrap` con scroll).
- **Sobrio y funcional**: menos decoración, menos "calidez", más contraste y eficiencia. El verde
  se usa para acción; el dorado solo para foco/acentos puntuales.
- **Tarjetas de estadística** (`.stats`) como KPIs claros: número grande, etiqueta sobria.
  (Si hay gráficos en el futuro, seguir una guía de dataviz con la paleta de marca.)
- **Estados claros**: badges de activo/bloqueado con color semántico (verde/rojo apagados, ya existen
  `.badge` y `.blocked`).
- **Diferenciador visual respecto al portal**: fondo más neutro/blanco (no tan "papel"), densidad mayor,
  sensación de "panel de control" vs. "escritorio personal".

---

## 6. Login (contexto, ya existe — opcional mejorar)

El login ya tiene una estructura de **dos columnas**: a la izquierda el mensaje
("Tu identidad. Todos tus sistemas.") y a la derecha la tarjeta con el formulario de DNI + contraseña.
Si se rediseña, mantener ese **split acogedor** y sumar la ilustración SVG de bienvenida en el lado
izquierdo. El campo clave es **DNI (8 dígitos)**; mensaje de "acceso para personal autorizado".

---

## 7. Principios transversales (resumen para Claude Diseño)

1. **Dos personalidades, un ADN**: portal = escritorio cálido y minimalista; admin = consola sobria y
   densa. Comparten paleta, sello "SJ" y serif en titulares.
2. **Institucional pero humano**: formal por la marca, cálido por el lenguaje y los redondeos.
3. **Minimalismo con intención**: pocas zonas, mucho aire, jerarquía fuerte. Nada se agrega "porque sí".
4. **Creativo y único**: la metáfora de "una llave, varias puertas" + la lanzadera tipo escritorio +
   guiños geométricos andinos lo hacen distinto a un dashboard genérico.
5. **SVG ligero, inline, en colores de marca.** Sin fotos de stock.
6. **Accesible y responsive por defecto**: foco dorado visible, contraste AA, táctil, móvil primero.
7. **Reusar lo que ya existe** como base: clases `.system-card`, `.stats`, `.badge`, `.card`, la paleta
   de `:root` en `sso/public/css/sso.css`, y la estructura de `layouts/app.blade.php`.

---

## 8. Entregable esperado de Claude Diseño

- Dos propuestas visuales (portal usuario / panel admin) coherentes entre sí pero diferenciadas.
- SVG inline para: sello "SJ", iconos de sistema, y textura/greca de fondo.
- Paleta refinada partiendo de la actual (verde `#146b50`, dorado `#bb8b3c`, tinta `#17372f`, papel `#f4f6f2`).
- Componentes clave maquetados: tarjeta lanzadora de sistema, carné de identidad del usuario,
  fila/tabla de admin, tarjeta KPI, y el login.
