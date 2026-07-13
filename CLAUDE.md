# Biblioteca UMAG — Contexto del proyecto para Claude Code

## Qué es esto

Reestructuración de un sistema de biblioteca universitaria (UMAG, Punta Arenas)
desde **Next.js + React + Tailwind** hacia **Vue 3 + Tailwind (frontend)** y
**Laravel + PostgreSQL (backend API)**, 100% dockerizado.

- **Repo actual (este):** el que estás por editar.
- **Repo original de referencia (solo lectura):**
  `https://github.com/Dysnomia9/biblioteca_sistema_docker_sumag`
  Úsalo para extraer **estructura de datos, textos, reglas de negocio y UX de
  referencia** — NO para copiar código React literal (el stack cambió a Vue).

## Estado del proyecto

Todos los módulos originalmente planeados (usuarios, entrada, préstamos,
salas, reportes) ya están implementados de punta a punta — no quedan rutas
placeholder tipo "Próximamente". Además existen dos capas de autenticación
separadas (`staff` y `usuario`) y un portal de autoservicio para usuarios
finales que no existía en el plan inicial. También hay catalogación de
libros (formato MARC/Dewey-lite, solo admin) con gestión de estado físico
del ejemplar ("Estado de Libro", todo staff) — es el primer punto del
sistema con un chequeo de rol real (`staff.rol`), ver convención 7 más
abajo. **No asumas que un módulo "falta"
por lo que digan README/tesis/documentación externa — verifica el código
real en `app-overlay/` y `frontend/src/` primero.**

## Stack

| Capa | Tecnología |
|---|---|
| Frontend | Vue 3 (Composition API, `<script setup>`), TypeScript, Vite, Pinia, Vue Router, Tailwind CSS, Axios |
| Backend | Laravel 11 (API-only), Sanctum (tokens Bearer, **no** sesión/cookie — ver nota abajo), PostgreSQL |
| Infra | Docker Compose: `frontend` (Vite dev server), `backend` (PHP-FPM/artisan serve), `db` (Postgres) |

## Cómo levantar el proyecto

```bash
docker compose up --build
docker compose exec backend php artisan mockup:datos      # primera vez / si no hay datos
docker compose exec backend php artisan mockup:datos --fresh  # regenerar todo desde cero
```

- Frontend: http://localhost:5173
- Backend API: http://localhost:8000/api
- Login staff: `admin@umag.cl` / `admin123`
- Portal usuario: `/portal/login` (usuarios generados por `mockup:datos`, con
  password seteada en el seeder)

## Estructura relevante

```
backend/
  docker-entrypoint.sh        # instala Laravel en frío la 1ra vez, aplica overlay, migra, seedea
  app-overlay/                # ÚNICO lugar del backend que se edita — se "hornea" en la imagen
    config/horizon_barcodes.php  # código de barras genérico de "puesto de trabajo" +
                                   mapeo codigo_barras->nombre de logia (para el comando de import)
    app/Models/
      Staff, Usuario, Entrada, Prestamo, Sala, Reserva, Libro, ReservaLibro, CodigoAcceso
    app/Http/Middleware/
      EnsureIsStaff, EnsureIsUsuario     # separan los dos guards de Sanctum
      EnsureIsAdmin (alias 'admin')      # chequea staff.rol === 'admin'; se aplica ADEMÁS
                                            de 'staff' (ej: ['auth:sanctum','staff','admin']),
                                            no lo reemplaza
    app/Http/Controllers/Api/
      AuthController, UsuarioAuthController   # login staff vs. login usuario (portal)
      DashboardController, UsuarioController, EntradaController, PrestamoController,
      SalaController, ReporteController, CodigoAccesoController, StaffController (GET /staff,
      solo para autocompletar "registrado/prestado/devuelto por" en el frontend)
      LibroController    # index/buscarPorCodigo (todo staff); store/update (solo admin,
                            catalogación MARC/Dewey-lite); cambiarEstado (todo staff,
                            gestiona libros.estado_proceso — ver Gotchas)
      ReservaLibroController  # reservas de libro (retiro)
      PortalController                         # endpoints del portal de autoservicio (/mi/*)
    app/Services/ReservaSalaService.php  # solapamiento de reservas + escanearLogia() (Horizon)
    app/Console/Commands/
      SeedMockupData.php (comando `mockup:datos`)
      ImportarCodigosLogia.php (comando `horizon:codigos-logia`, backfill de
        salas.codigo_barras real desde config/horizon_barcodes.php cuando Horizon los entregue)
    database/migrations/       correlativas por fecha; ver Deuda técnica más abajo
    routes/api.php             grupo `auth:sanctum + staff` y grupo `auth:sanctum + usuario`
    bootstrap/app.php          # NO tiene statefulApi() — auth es Bearer token puro, sin CSRF

frontend/
  src/
    views/            LoginView, LoginV2View, DashboardView, EntradaView, PrestamoView,
                       ListadoPrestamosView, ListadoLibrosView, UsuariosView, SalasView,
                       ReportesView, CodigoQrView, CatalogacionLibrosView (solo admin,
                       meta.requiresAdmin), EstadoLibroView
    views/portal/      PortalLoginView, PortalHomeView, PortalEntradaView,
                       PortalCatalogoView, PortalSalasView
    components/layout/  StaffLayout, TopBar (navegación + dropdown "Gestiones Admin" con
                        Usuarios/Listado Préstamos/Listado Libros/Código QR, ver convención 6
                        más abajo — no hay un componente "SidebarNav" separado), PortalLayout
    components/reportes/  BarChart, BreakdownList, ReporteTabla
    components/ApiErrorBanner.vue  Aviso "no se pudo conectar" — NO hay fallback a datos ficticios
    stores/           auth.ts (staff), usuarioAuth.ts (portal) — dos stores de Pinia separados
    services/         api.ts (staff, Bearer token de auth.ts), apiUsuario.ts (portal)
    composables/      useRut.ts, useToast.ts, useStaffShortcuts.ts (atajos de teclado del staff),
                       useStaffNombres.ts (cachea GET /staff para datalists de "registrado por")
    router/index.ts   dos guards: rutas `meta.portal` usan usuarioAuth, el resto usa auth;
                       además `meta.requiresAdmin` redirige a dashboard si
                       `auth.staff?.rol !== 'admin'`
    types/index.ts    Tipos TS que reflejan los modelos de Laravel
```

## Convenciones a seguir en módulos nuevos o cambios

1. **Capa Vue:** cada módulo staff es una vista en `src/views/`, envuelta en
   `<StaffLayout>`; los del portal van en `src/views/portal/` envueltos en
   `<PortalLayout>`. Cada uno con su propio store en Pinia si maneja estado
   propio de CRUD. Seguir el patrón de `DashboardView.vue`: `onMounted` llama
   a la API real vía `api.ts` (o `apiUsuario.ts` en el portal); si falla,
   muestra `<ApiErrorBanner />` ("No se pudo conectar con el servidor. No se
   están mostrando datos.") y deja los datos vacíos — **nunca** mostrar datos
   ficticios/mock como si fueran reales. Este patrón se usó antes (fallback a
   `data/mock.ts`) y se eliminó a propósito porque confundía a los usuarios;
   no lo reintroduzcas.
2. **Responsive:** mobile-first con Tailwind (`grid-cols-2 sm:grid-cols-3
   lg:grid-cols-5`, etc.). Nada de tablas que rompan el layout en mobile —
   usar scroll horizontal contenido o cards apiladas.
3. **Paleta:** usar los colores `biblioteca-*` y `acento-*` definidos en
   `tailwind.config.js` — no reintroducir los gradientes morados/índigo tipo
   SaaS del proyecto original.
4. **Backend:** un Controller + rutas protegidas por `auth:sanctum` + (`staff`
   o `usuario`) por módulo. Si dos controladores necesitan la misma regla de
   negocio (p. ej. el chequeo de solapamiento de reservas en
   `ReservaSalaService`), extráela a un `app/Services/` compartido en vez de
   duplicar la lógica — ya pasó una vez y costó un bug real (ver Deuda
   técnica). Cualquier migración nueva va con timestamp correlativo en
   `database/migrations/` (nunca editar una migración ya aplicada — crear una
   nueva `alter table` si hace falta cambiar un esquema existente).
5. Los links de navegación viven en `TopBar.vue` y las rutas en
   `router/index.ts` — ya apuntan a los componentes reales, no hay
   `ProximamenteView` que reemplazar. Los módulos secundarios (Usuarios,
   Listado Préstamos, Listado Libros, Código QR) están agrupados en el
   dropdown "Gestiones Admin" (array `adminLinks` en `TopBar.vue`), no como
   links planos en la barra.
6. **Dropdowns/menús flotantes en `TopBar.vue`**: el `<nav>` de navegación
   usa `overflow-x-auto` para el scroll horizontal en mobile, y por spec CSS
   eso fuerza a que el overflow vertical también quede recortado (no se
   puede mezclar `overflow-x: auto` con `overflow-y: visible` en el mismo
   elemento). Cualquier panel flotante que cuelgue de un botón dentro de ese
   `<nav>` debe ir en un `<Teleport to="body">` posicionado con
   `getBoundingClientRect()` del botón (ver `adminMenuOpen`/`adminMenuPos` en
   `TopBar.vue`) — si lo pones como `absolute` dentro del `<nav>`, queda
   encerrado y aparece un scroll para verlo en vez de flotar por encima.
7. **Restringir una acción a `rol = 'admin'`**: backend, agregar el
   middleware `admin` al grupo de rutas (junto a `staff`, no en su lugar —
   ver `App\Http\Middleware\EnsureIsAdmin`); frontend, marcar la ruta con
   `meta: { requiresAdmin: true }` en `router/index.ts` (el guard ya
   redirige a dashboard si no corresponde) y, si el link vive en
   `TopBar.vue`, agregar `adminOnly: true` a esa entrada de `adminLinks`
   (ya se filtra con `adminLinksVisibles()`). Las tres capas son necesarias:
   el middleware es la única que realmente protege, las otras dos son UX.

## Deuda técnica conocida (no asumir que ya se resolvió sin verificar el código)

- **`Prestamo.libro_titulo` ya NO es texto libre para libros del catálogo**
  (resuelto): `PrestamoController::store()` exige `codigo_barras`, busca el
  `Libro` real, valida `disponible` (409 si ya está prestado/reservado por
  otra persona) y guarda `libro_id` (FK real, `Prestamo::libro()`) + copia de
  `libro_titulo`/`codigo_barras`. Al devolver, libera el libro
  (`disponible = true`) buscándolo por `libro_id`. **Pero los equipos**
  (`tipo_item = audifonos|notebook`) siguen siendo 100% texto libre por
  código de inventario (`AUD-003`, `NB-012`) — no están en la tabla `libros`
  y es intencional, no una laguna. No hay todavía un modelo `Ejemplar` (un
  mismo `Libro` sigue siendo una sola fila con un solo `disponible`, no
  soporta múltiples copias físicas).
- **`Reserva.ruts` es un array JSON** (cast `array` en el modelo) en vez de
  una tabla relacional `reserva_participantes`. `SalaController::index`
  reconstruye manualmente el mapeo RUT → usuario porque no hay relación
  Eloquent real.
- **Sí hay tests automatizados** (`backend/app-overlay/tests/Feature/`:
  `AuthTest`, `UsuarioAuthTest`, `MiddlewareTest`, `EntradaTest`,
  `SalaReservaTest`, `SalaDevolucionTest`, `PortalReservaTest`,
  `PortalEntradaTest`), corren contra una DB Postgres dedicada
  (`biblioteca_test`, ver `docker-entrypoint.sh`) con
  `docker compose exec backend php artisan test`. No cubren todavía
  `LibroController` ni la catalogación/`estado_proceso`.
- **`Libro`: dos ejes de estado independientes** — `disponible` (boolean,
  circulación: ¿está prestado/reservado ahora mismo?) y `estado_proceso`
  (string, procesamiento bibliotecario: `inventario` | `procesos_tecnicos` |
  `por_colocar` | `en_estante` | `estanteria_auxiliar` | `de_baja`). Un libro
  solo es prestable/reservable si `estado_proceso === 'en_estante'` **y**
  `disponible === true` — no colapses estos dos campos en uno ni le des a
  `disponible` significados de estado físico, rompería
  `PrestamoController`/`ReservaLibroController`. Se dejaron fuera a
  propósito (ver commit de catalogación): contadores de préstamos
  históricos/última fecha (derivables por query sobre `prestamos`), y la
  separación registro bibliográfico vs. ejemplar físico ("Bib No."/"Item
  No." estilo Horizon) — sigue siendo 1 fila `Libro` = 1 copia física.
- **Credenciales de Postgres hardcodeadas** en `docker-compose.yml`
  (`biblioteca`/`biblioteca`) — aceptable para desarrollo local, no usar tal
  cual como base de un despliegue.
- **`PortalController` concentra varias responsabilidades** (estado/aforo,
  entrada/salida, catálogo, salas y reservas del usuario). Si crece más,
  conviene separar por dominio en vez de agregar más métodos ahí.

## Gotchas ya resueltos (no los reintroduzcas)

- **Volumen Docker + Composer**: `composer create-project` falla si el
  directorio destino no está vacío (Docker mete un `lost+found` en volúmenes
  nuevos sobre ext4). El entrypoint instala en `/tmp/laravel-fresh` y luego
  copia — no cambies esto a instalar directo en `/var/www`.
- **Composer audit block**: Composer 2.8+ bloquea instalaciones por
  advisories de seguridad por defecto. El entrypoint usa `--no-audit` y la
  imagen define `COMPOSER_NO_AUDIT=1`. No lo quites o `create-project`
  volverá a fallar.
- **`.env` con SQLite en vez de Postgres**: `composer create-project` genera
  su propio `.env` (sqlite) antes de que apliquemos el nuestro. El
  entrypoint sobreescribe `.env` con nuestra plantilla (`app-overlay/.env.example`)
  inmediatamente después de instalar Laravel, dentro del bloque `if [ ! -f
  "artisan" ]`. Si tocas ese bloque, no rompas ese orden.
- **CSRF "token mismatch" en el login**: `bootstrap/app.php` NO llama
  `$middleware->statefulApi()`. La auth es 100% Bearer token (Sanctum
  Personal Access Tokens), sin cookies de sesión — si se reactiva
  `statefulApi()` sin implementar el flujo de cookie CSRF completo en el
  frontend, el login vuelve a romperse.
- **Sin rate limiting en login**: ya se agregó `throttle:6,1` en las rutas
  `POST /auth/login` y `POST /auth/usuario/login` (`routes/api.php`) — no lo
  quites, es la única protección contra fuerza bruta que tiene el sistema
  ahora mismo.
- **Seed no destructivo**: el entrypoint corre `migrate --force` (no
  `migrate:fresh`) y solo ejecuta `mockup:datos` automáticamente si la tabla
  `staff` está vacía. Los datos de prueba ya NO se borran en cada
  `docker compose up`.
- **Solapamiento de reservas de sala**: la comparación original solo
  chequeaba `hora_inicio` exacto (dos reservas 10-12 y 11-13 no se detectaban
  como conflicto). Ya se corrigió con intersección real
  (`hora_inicio < fin && hora_fin > inicio`) centralizada en
  `App\Services\ReservaSalaService::existeSolapamiento()`, usado por
  `SalaController` y `PortalController`. No reintroduzcas la comparación
  exacta ni la lógica duplicada en un controlador nuevo.
- **Entradas duplicadas / sin cierre de salida**: antes se podía registrar
  una entrada nueva sin haber cerrado la anterior, inflando el conteo de
  "personas en sala" indefinidamente (no había forma de marcar salida desde
  la API). Ya se agregó: validación de entrada activa antes de crear una
  nueva (409 si ya existe) en `EntradaController::store/storeExterno` y
  `PortalController::registrarEntrada`, más los endpoints
  `PATCH /entrada/{entrada}/salida` (staff) y `POST /mi/salida` (portal).
- **RUT repetido / doble reserva de sala para la misma persona**: antes se
  podía enviar el mismo RUT dos veces en el array `ruts` de una reserva, y un
  mismo RUT podía terminar reservado en dos salas distintas al mismo tiempo
  (nada lo impedía). Ya se agregó: regla `distinct` en `ruts.*` (rechaza RUT
  duplicado dentro de la misma reserva) y
  `ReservaSalaService::participanteConReservaSolapada()` (busca, con
  `whereJsonContains` sobre cualquier sala, si alguno de los RUT ya tiene una
  reserva con horario solapado ese día) en `SalaController::storeReserva` y
  `PortalController::reservarSala`. No reintroduzcas la validación sin este
  chequeo cruzado entre salas.
- **`SalaController::storeReserva` aceptaba RUT externos (no registrados)**:
  a diferencia del portal (`PortalController::reservarSala`, que siempre
  exigió `exists:usuarios,rut`), el endpoint de staff no validaba que cada
  RUT del array `ruts` perteneciera a un usuario real — se podía reservar una
  logia con RUT inventados. Ya se igualó la regla (`ruts.*` ahora incluye
  `exists:usuarios,rut` también en `SalaController`). Los visitantes externos
  NO pueden reservar logias, solo registrar entrada (`/entrada/externo`).
- **Reserva/préstamo de un libro ya ocupado por otra persona**: ni
  `ReservaLibroController::store()` ni `PrestamoController::store()`
  chequeaban `Libro.disponible` antes de crear el registro — se podía
  reservar o prestar el mismo libro dos veces en paralelo. Ya se agregó: el
  campo `libros.disponible` ahora es la fuente de verdad compartida entre
  reservas y préstamos de libro — ambos endpoints devuelven 409 si
  `disponible = false`, lo ponen en `false` al crear el registro, y lo
  vuelven a `true` al cancelar la reserva o devolver el préstamo. No
  reintroduzcas ninguno de los dos flujos sin este chequeo cruzado.
- **Integración de códigos de barra Horizon (logias, puestos de trabajo,
  visitas de convenio)**: el sistema convive con Horizon, que ya usa códigos
  de barra reales. Diseño implementado:
  - **Logias**: cada `Sala` tiene su propio `codigo_barras` único
    (`tipo = 'logia'`). `ReservaSalaService::escanearLogia()` (usado por
    `SalaController::scanLogia`, `POST /salas/scan-logia`) hace check-in/
    check-out sobre la `Reserva` vigente de esa sala: el primer escaneo
    marca `prestado_por` + `hora_prestamo_real` + `via='BC'`; el segundo
    marca `devuelto_por` + `hora_devolucion_real` + `estado='finalizada'`.
    No crea reservas nuevas, solo cierra el ciclo de una ya existente.
  - **Puestos de trabajo**: Horizon reutiliza un puñado de códigos
    genéricos para todos los puestos — por eso NO se modelan como `Sala` ni
    se le pide el código a nadie: cada `Entrada` creada por
    `EntradaController::store/storeExterno/storeConvenio` se estampa
    automáticamente con `codigo_barras = config('horizon_barcodes.puesto_generico')`.
    Es una marca de asistencia, no una reserva de recurso — no exigas que el
    staff tipee o escanee ese código en el frontend.
  - **Convenio**: tercera categoría de entrada (junto a usuario interno y
    "Externo"), mismo flujo que `storeExterno` pero con `es_convenio = true`
    para diferenciarla en reportería/UI (badge "Convenio" en `EntradaView.vue`).
  - Los códigos reales de Horizon (por logia, y el genérico de puesto) no
    estaban disponibles al implementar esto — `config/horizon_barcodes.php`
    trae un placeholder inventado (`'62572'`) y el comando
    `horizon:codigos-logia` para cargar el mapeo real cuando se tenga, sin
    tocar código.
- **Catalogación de libros sin control de estado físico**: antes no existía
  forma de crear libros desde la UI (`LibroController` solo tenía
  `index`/`buscarPorCodigo`) ni de distinguir un libro recién ingresado (aún
  en inventario/procesos técnicos) de uno realmente disponible en estante.
  Ya se agregó `libros.estado_proceso` (ver Deuda técnica) +
  `LibroController::store/update` (solo admin, `/libros/catalogacion`) +
  `cambiarEstado` (todo staff, `/libros/estado`). `PrestamoController` y
  `ReservaLibroController` ya exigen `estado_proceso === 'en_estante'`
  además de `disponible`, y `PortalController::catalogo()` ya filtra por
  `en_estante`. No reintroduzcas un `POST /libros` que no dependa de este
  chequeo, ni dejes que un libro recién catalogado sea prestable por
  defecto (nace en `inventario`, no en `en_estante`).
- **Reservas de logia en horas no redondas (ej. alguien llega a las
  14:30)**: se evaluó pasar a horarios libres en minutos y se descartó a
  propósito — los bloques fijos de 2h (08-10, 10-12, ...) son una regla de
  negocio (equidad de uso) y el escaneo Horizon de logias
  (`ReservaSalaService::escanearLogia()`) ya calza con esa cadencia fija; no
  es una limitación técnica a "arreglar". Lo que sí se agregó es UX: la
  columna del bloque vigente se resalta ("Ahora") y hay un botón "Reservar
  ahora" en `SalasView.vue`/`PortalSalasView.vue` que preselecciona ese
  bloque en la primera sala libre — la hora real de inicio queda en
  `hora_prestamo_real`, separada del bloque nominal.

## Checklist antes de dar un módulo por terminado

- [ ] Responsive real probado en mobile (no solo con dev tools, si es posible)
- [ ] La vista muestra `<ApiErrorBanner />` si la API falla — sin datos ficticios
- [ ] Las rutas nuevas del backend están protegidas con `auth:sanctum` + el
      middleware de guard correcto (`staff` o `usuario`)
- [ ] Si hay migración nueva, es un archivo nuevo (no editaste una existente)
- [ ] El link de navegación en `TopBar.vue` y la ruta en `router/index.ts`
      quedaron conectados al componente real
- [ ] Si duplicaste una regla de negocio en dos controladores, extráela a un
      `App\Services\` compartido en vez de dejarla repetida
- [ ] `docker compose exec backend php artisan mockup:datos --fresh` sigue
      corriendo sin errores después del cambio
