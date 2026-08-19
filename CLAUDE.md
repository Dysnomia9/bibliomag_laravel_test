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
finales que no existía en el plan inicial.

**Catalogación de libros, ampliada a fondo el 2026-08-15**: el modelo
`Libro` (registro bibliográfico — título/ISBN/autores/categorías/carreras,
estas tres últimas N:M vía `Autor`/`Categoria`/`Carrera`) se separó de
`Ejemplar` (copia física — código de barras propio, `numero_copia`,
`estado_proceso`, `ubicacion_id`, disponibilidad) — un libro ya puede tener
varias copias reales, cerrando el gap #2 del checklist de Horizon de abajo.
Encima de eso se agregaron: estados de ejemplar personalizados
(administrables, más allá de los 6 fijos), cambio masivo de estado con
confirmación seria ("escribe CONFIRMAR"), historial de cambios de estado
por ejemplar, historial de préstamos por libro/copia, generador de código
de barras secuencial, catálogo de ubicaciones administrable, reportes
nuevos (top de libros prestados, uso de logias por sala + heatmap hora×sala),
botón "Visita" en Entrada, Configuración Institucional editable +
Constancia de No Multa en PDF (client-side, `jsPDF`), generable tanto desde
Usuarios (staff, por RUT) como en autoservicio desde el portal virtual
(`GET /mi/multas`), y un flujo de confirmación de asistencia de 15 minutos
para reservas de sala con liberación automática por no-show (más la
restricción de que un alumno solo puede reservar sala para el día de hoy)
— ver Gotchas para el detalle de cada uno. **Actualización (2026-08-16)**:
los equipos (audífonos/notebooks/cargadores — este último tipo nuevo) ya
no se prestan tipeando un código de inventario en texto libre, se prestan
escaneando `codigo_barras` real, igual que los libros — ver Gotchas. Es el
primer punto del sistema con un chequeo de rol real (`staff.rol`) más allá
de la catalogación misma, ver convención 7 más abajo.

**No asumas que un módulo "falta" por lo que digan README/tesis/
documentación externa — verifica el código real en `backend/` y
`frontend/src/` primero**, y no asumas que esta lista sigue completa sin
volver a mirar el código si pasó tiempo desde 2026-08-15.

## Cobertura funcional vs. Horizon (checklist de evaluación de tesis)

Checklist de referencia para comparar este sistema contra las funciones que
la UMAG usa realmente de Horizon (evaluación honesta hecha sobre el código
real el 2026-07-17 — no asumir que sigue así sin volver a verificar). Un
profesor evaluando la tesis probablemente busque estos 13 puntos:

| # | Función | Estado | Evidencia / notas |
|---|---|---|---|
| 1 | Gestión de libros | ✅ Completo | `LibroController::index/store/update/cambiarEstado`, `CatalogacionLibrosView.vue` (solo admin), catalogación MARC/Dewey-lite |
| 2 | Gestión de ejemplares | ✅ Completo (desde 2026-08-15) | Modelo `Ejemplar` real, separado de `Libro` (obra) — soporta múltiples copias del mismo título (`numero_copia`, código de barras propio por copia), cada una con su `estado_proceso` independiente (`EstadoLibroView.vue`, `EjemplarController::cambiarEstado`), estados personalizados administrables, cambio masivo de estado, e historial de cambios (`EjemplarEstadoHistorial`) |
| 3 | Préstamos | ✅ Completo | `PrestamoController::store`, incluye cálculo de multa por atraso desde 2026-07-17 (ver Gotchas) |
| 4 | Devoluciones | ✅ Completo | `PrestamoController::devolver` |
| 5 | Reservas | ✅ Completo | Salas/logias (`SalaController` + `ReservaSalaService`) y libros para retiro (`ReservaLibroController` + `PortalReservaLibroController`, con cola de espera FIFO — ver Gotchas) |
| 6 | Historial | ✅ Completo (desde 2026-08-19) | Por usuario: completo (`PrestamoView.vue` lista todos sus préstamos y reservas, no solo los activos). Global de préstamos: `ListadoPrestamosView.vue` sin filtro de fecha (brecha menor, no crítica). Por libro/copia: `LibroController::historial` + `HistorialPrestamosLibroView.vue`. Estado de ejemplares: `EjemplarController::historialEstado` + `HistorialEstadoLibroView.vue`. Entradas: ya no es el eslabón débil — `EntradaController::index` ahora soporta modo búsqueda (`desde`/`hasta`/`q` por RUT o nombre, ver Gotchas) además del modo día exacto de siempre |
| 7 | Dashboard | ✅ Completo | `DashboardController::resumen` + `DashboardView.vue` |
| 8 | Reportes | ✅ Completo | `ReporteController` (agregaciones `GROUP BY` por período/carrera/sexo/tipo/hora), `ReportesView.vue` con gráficos |
| 9 | Estadísticas | ✅ Cubierto (dentro de Dashboard + Reportes) | No hay un menú "Estadísticas" separado, pero los desgloses (`porCarrera`, `porSexo`, `porAnioIngreso`, `porTipoUsuario`, `porHora`) existen en `ReporteResumen` |
| 10 | Búsqueda avanzada | ✅ Completo (desde 2026-08-19) | Usuarios (`UsuarioController::index`): multi-campo real (`nombre`/`apellido`/`rut`/`carrera`) + filtros `tipo`/`activo`. Préstamos: filtros `usuario_id`/`estado`/`tipo_item`. Libros (`LibroController::index`): filtros reales en backend — `categoria_id`/`autor_id`/`carrera_id`/`tipo_material_id`/`estado_proceso` (+ `estado_personalizado_id`), además del texto libre por título/ISBN/autor/código de cualquier copia; `CambioMasivoEstadoView.vue` suma un filtro de ubicación, pero para operaciones masivas, no para búsqueda. Entradas ya no es el más débil: `q` busca por RUT/nombre (usuario registrado o externo) combinado con rango `desde`/`hasta`, ver Gotchas |
| 11 | Consulta de disponibilidad | ✅ Completo | `Ejemplar.disponible` + `estado_proceso` (por copia, desde el split de Libro/Ejemplar), `EjemplarController::buscarPorCodigo` (chequeo en tiempo real al prestar/reservar), disponibilidad de salas por bloque horario (`GET /salas?fecha=`), catálogo del portal filtrado por disponibilidad agregada de las copias de cada título |
| 12 | Integración con la base institucional | ⚠️ Parcial — ojo con este punto en la defensa | **No es una integración de datos/API real con Horizon** (no hay sync ni llamadas a una BD/API externa). Es una capa de **compatibilidad de códigos de barra** para convivir físicamente con los lectores Horizon: `config/horizon_barcodes.php`, `ReservaSalaService::escanearLogia()`, comando `horizon:codigos-logia`. Los códigos reales de Horizon **todavía no están cargados** (placeholder inventado `'62572'`). No confundir con el botón "Base de Datos Digital"/"Recursos Digitales" (`UsuariosView.vue`, `PortalHomeView.vue`, agregado 2026-08-15) — es solo un link de salida a `https://umag.elogim.com/` (catálogo externo de recursos digitales de la universidad), sin ninguna integración de datos, sin relación con Horizon |
| 13 | QR | ✅ Completo y funcional | `CodigoAcceso` (código de acceso compartido, regenerable), renderizado real con la librería `qrcode` (`CodigoQrView.vue`, canvas + descarga PNG), validado server-side en `PortalController::registrarEntrada` cuando `via === 'qr'`. Nota: el campo `Usuario.qr_code` es vestigial, no forma parte de este flujo |

**Resumen honesto (actualizado 2026-08-19)**: 12/13 sin reservas, 1/13 con
brecha real y verificable en el código (integración real con Horizon más
allá de compatibilidad de código de barras — punto 12, sin sync/API real,
código de logia todavía placeholder). El gap de ejemplares múltiples
(punto 2) se cerró el 2026-08-15 con el modelo `Ejemplar`; los dos gaps de
Entradas (historial sin rango/búsqueda, punto 6, y búsqueda avanzada,
punto 10) se cerraron el 2026-08-19 (ver Gotchas). El propio profesor
considera fuera de alcance razonable adquisiciones/seriales/proveedores/
multas avanzadas — las multas básicas (tarifa fija por día, $15 desde
2026-08-15, sin bloqueo de nuevos préstamos por deuda) ya están cubiertas
por el punto 3. El punto 12 (Horizon) es una limitación real de datos (no
hay credenciales/API de Horizon disponibles), no una brecha de código
pendiente de implementar — no asumir que se puede "simplemente
completar" sin acceso al sistema real. Antes de citar un "% de
cobertura" en la defensa, volver a correr esta tabla contra el código si
pasó tiempo desde 2026-08-19.

## Stack

| Capa | Tecnología |
|---|---|
| Frontend | Vue 3 (Composition API, `<script setup>`), TypeScript, Vite, Pinia, Vue Router, Tailwind CSS, Axios |
| Backend | Laravel 12 (API-only — Laravel 11 se descartó por advisories de Composer en `laravel/framework`), Sanctum (tokens Bearer, **no** sesión/cookie — ver nota abajo), PostgreSQL |
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

`docker-compose.yml` es solo para desarrollo (bind mount del frontend,
credenciales hardcodeadas). Para un despliegue real existe
`docker-compose.prod.yml` (sin bind mounts, sin credenciales hardcodeadas —
vienen de variables de entorno del host/CI, sin exponer el puerto de
Postgres) + `backend/.env.production.example` (plantilla, valores
`__REEMPLAZAR__`, nunca commitear el `.env.production` real — ya está en
`.gitignore`). Ver "Producción real" en Gotchas más abajo.

## Estructura relevante

`backend/` es un proyecto Laravel **normal y completo** — no hay overlay ni
paso de instalación en frío (ver "Ya no existe `app-overlay/`" en Gotchas).
Se edita directo ahí, igual que cualquier proyecto Laravel: `app/`,
`config/`, `database/`, `routes/`, `tests/`, `bootstrap/app.php`. El resto
(`public/`, `resources/`, `storage/`, `vendor/`, `artisan`, `composer.json`)
es el esqueleto estándar de Laravel — no lo reestructures sin necesidad.

```
backend/
  Dockerfile                  # composer install + copia el proyecto completo en tiempo de BUILD
  docker-entrypoint.sh        # solo runtime: .env si falta, migra, seedea si hace falta, sirve
  config/horizon_barcodes.php  # código de barras genérico de "puesto de trabajo" +
                                 mapeo codigo_barras->nombre de logia (para el comando de import)
  config/multas.php            # tarifa/gracia/tope de la multa por atraso — $15/día desde
                                 2026-08-15 (antes $300) — ajustar acá, nunca hardcodear el
                                 monto en el controller/service
  config/database.php          # DB_SSLMODE ya es env()-driven (default 'prefer'); en
                                  .env.production.example se fuerza a 'require'
  config/reservas_libro.php    # días para retirar un libro una vez apartado
                                  (reserva directa o promoción desde la cola) — ajustar
                                  acá, nunca hardcodear en el service
  app/Providers/AppServiceProvider.php  # RateLimiter::for('api', ...) — 60/min por
                                  usuario autenticado o IP, aplicado a toda la API vía
                                  $middleware->throttleApi() en bootstrap/app.php
  app/Models/
    Staff (con `activo`), Usuario, Entrada (con `es_visita`), Prestamo (con
    prestado_por_staff_id/devuelto_por_staff_id/multa_pagada_por_staff_id,
    ejemplar_id — FK reales), Sala, Reserva (con plazoConfirmacion()/
    estaVencidaSinConfirmar(), ver Gotchas), Libro (registro bibliográfico:
    autores()/categorias()/carreras()/ejemplares(), N:M vía pivotes),
    Ejemplar (copia física: libro()/estadoPersonalizado()/ubicacion()/
    prestamos()/reservasLibro()/historialEstado()), ReservaLibro,
    Equipo, CodigoAcceso, Autor, Categoria, Carrera (catálogos "escribe y
    crea", ver convención 8), EstadoLibroPersonalizado, Ubicacion
    (catálogos admin-only, ver convención 8), EjemplarEstadoHistorial,
    ConfiguracionInstitucional (fila única id=1, nombre/cargo de quien
    firma la Constancia de No Multa)
  app/Http/Middleware/
    EnsureIsStaff, EnsureIsUsuario     # separan los dos guards de Sanctum
    EnsureIsAdmin (alias 'admin')      # chequea staff.rol === 'admin'; se aplica ADEMÁS
                                          de 'staff' (ej: ['auth:sanctum','staff','admin']),
                                          no lo reemplaza
  app/Http/Controllers/Api/
    AuthController, UsuarioAuthController   # login staff vs. login usuario (portal)
    DashboardController, UsuarioController, EntradaController (incluye
    storeVisita()), PrestamoController, SalaController (incluye
    confirmarLlegada()/liberarReserva(), ver Gotchas), ReporteController
    (incluye multasPendientes(), y en resumen() los tabs 'libros'/'logias'
    con porLibro/porSala/porHoraPorSala), CodigoAccesoController,
    StaffController (GET /staff, solo para autocompletar "registrado/
    prestado/devuelto por" en el frontend), EquipoController (catálogo de
    audífonos/notebooks/cargadores — buscarPorCodigo todo staff,
    store/cambiarActivo solo admin, ver Gotchas 2026-08-16)
    LibroController    # opera SOLO sobre el registro bibliográfico (obra):
                          index (filtros q/categoria_id/autor_id/carrera_id/
                          tipo_material/estado_proceso)/show/store/update
                          (solo admin, catalogación MARC/Dewey-lite + crea
                          el primer Ejemplar en la misma transacción)/
                          historial() (préstamos por copia, todo staff)
    EjemplarController  # opera sobre la copia física: buscarPorCodigo/store
                          ("agregar copia" a un libro existente, solo admin)/
                          siguienteCodigoBarras (generador secuencial UMAG######,
                          solo admin)/cambiarEstado (todo staff)/
                          cambioMasivoPreview+cambioMasivoEjecutar (solo
                          admin, exige al menos un filtro, escribe
                          ejemplar_estado_historial con lote_id compartido)/
                          historialEstado
    CatalogoLibroController  # autores()/categorias()/carreras() — GET simple,
                                cualquier staff, "escribe y crea" vía
                                firstOrCreate en LibroController::sincronizarPivotes
    EstadoLibroPersonalizadoController  # index (todo staff)/store+cambiarActivo
                                           (solo admin) — catálogo administrable,
                                           ver convención 8
    UbicacionController  # index (todo staff)/store (solo admin) — catálogo
                            administrable, ver convención 8
    ConfiguracionInstitucionalController  # show (todo staff, lo necesita
                                             cualquiera generando la
                                             constancia)/actualizar (admin)
    ReservaLibroController  # reservas de libro (retiro) + cola de espera, todo staff
    PortalController                         # endpoints del portal virtual de autoservicio (/mi/*):
                                                estado/aforo, entrada/salida, catálogo, salas
                                                (reservarSala() exige fecha = hoy, ver Gotchas),
                                                misMultas() (autoservicio de Constancia de No
                                                Multa, acotado al usuario autenticado)
    PortalReservaLibroController              # autoservicio de reservas de libro del portal
                                                 virtual (/mi/reservas-libro) — se separó de
                                                 PortalController a propósito (ver Deuda técnica)
                                                 en vez de seguir agregándole métodos
  app/Services/ReservaSalaService.php  # solapamiento de reservas (relacional, ver Gotchas) +
                                          escanearLogia() (Horizon) + registrarLlegada()/
                                          liberarPorNoPresentacion()/liberarSiVencida()
                                          (confirmación de asistencia de 15 min, ver Gotchas)
  app/Services/MultaService.php        # calcula la multa por atraso al devolver un libro
  app/Services/ReservaLibroService.php # reservar/encolar un libro por código de barras
                                          (reservarOEncolar) o por libro_id desde el catálogo
                                          agregado del portal (reservarOEncolarPorLibro),
                                          cancelar, y liberarLibro() (promueve la cola de
                                          espera) — compartido entre ReservaLibroController
                                          (staff) y PortalReservaLibroController (portal virtual)
  app/Console/Commands/
    SeedMockupData.php (comando `mockup:datos`)
    ImportarCodigosLogia.php (comando `horizon:codigos-logia`, backfill de
      salas.codigo_barras real desde config/horizon_barcodes.php cuando Horizon los entregue)
  database/migrations/       correlativas por fecha; ver Deuda técnica más abajo.
                              El bloque `2024_01_03_0000XX` es el de
                              "producción real" (CHECK constraints, índices
                              de FK faltantes, cascadas RESTRICT, atribución
                              de staff, staff.activo). El bloque
                              `2026_08_14_0000XX`/`2026_08_15_0000XX` es el
                              del split Libro/Ejemplar + catálogos nuevos —
                              ver Gotchas
  routes/api.php             grupo `auth:sanctum + staff` y grupo `auth:sanctum + usuario`
  bootstrap/app.php          # NO tiene statefulApi() — auth es Bearer token puro, sin CSRF

frontend/
  src/
    views/            LoginView (título "Administración"), LoginV2View, DashboardView,
                       EntradaView (con botón "Visita"), PrestamoView, ListadoPrestamosView,
                       ListadoLibrosView, UsuariosView (con "Base de Datos Digital" y
                       "Constancia de No Multa" por fila), SalasView (con menú de
                       confirmación de asistencia), ReportesView (tabs incl. "Top de
                       Libros", heatmap de logias), CodigoQrView, CatalogacionLibrosView
                       (solo admin, meta.requiresAdmin — tabs "libro nuevo"/"agregar
                       copia"), EstadoLibroView, CambioMasivoEstadoView (solo admin,
                       meta.requiresAdmin), HistorialEstadoLibroView,
                       HistorialPrestamosLibroView, EquiposView (solo admin,
                       meta.requiresAdmin), MultasPendientesView, AdministracionView
                       (solo admin, meta.requiresAdmin — configuración institucional +
                       estados personalizados + ubicaciones)
    views/portal/      PortalLoginView (título "Inicio de Sesión Usuarios"),
                       PortalHomeView (con card "Recursos Digitales" y botón
                       autoservicio "Constancia de No Multa"), PortalEntradaView,
                       PortalCatalogoView, PortalSalasView (reserva solo para hoy, sin
                       selector de fecha)
    components/layout/  StaffLayout, TopBar (navegación + dropdown "Gestiones Admin",
                        ver convención 6 más abajo — no hay un componente "SidebarNav"
                        separado), PortalLayout
    components/libros/  LibrosModuloNav (nav cruzada entre las 7 vistas del módulo de
                        libros), MultiSelectCombobox (input "escribe y crea" para
                        autores/categorías/carreras, ver convención 8)
    components/reportes/  BarChart, BreakdownList, ReporteTabla, Heatmap (grid sala×hora
                        con rampa de color secuencial, uso de logias)
    components/ApiErrorBanner.vue  Aviso "no se pudo conectar" — NO hay fallback a datos ficticios
    utils/constancia.ts  generarConstanciaNoMulta() — arma el PDF con jsPDF en el
                        navegador (sin logo institucional como imagen, encabezado de
                        texto), llamado desde UsuariosView.vue (ver Deuda técnica: solo
                        lado staff, no hay autoservicio en el portal)
    stores/           auth.ts (staff), usuarioAuth.ts (portal) — dos stores de Pinia separados
    services/         api.ts (staff, Bearer token de auth.ts), apiUsuario.ts (portal)
    composables/      useRut.ts, useToast.ts, useStaffShortcuts.ts (atajos de teclado del staff),
                       useStaffNombres.ts (cachea GET /staff para el datalist "registrado por"
                       de SalasView.vue — ya NO se usa en Préstamos, ver Gotchas de atribución
                       de staff)
    router/index.ts   dos guards: rutas `meta.portal` usan usuarioAuth, el resto usa auth;
                       además `meta.requiresAdmin` redirige a dashboard si
                       `auth.staff?.rol !== 'admin'`
    types/index.ts    Tipos TS que reflejan los modelos de Laravel — incluye Libro/
                       Ejemplar separados, Autor/Categoria/Carrera/Ubicacion/
                       EstadoLibroPersonalizado, ConfiguracionInstitucional
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
8. **Catálogo administrable nuevo (tipo Autor/Categoría/Ubicación)**: hay dos
   patrones distintos según quién puede crear valores nuevos — no los
   mezcles.
   - **"Escribe y crea" (`Autor`, `Categoria`, `Carrera`)**: cualquier staff
     puede crear un valor nuevo al vuelo desde el formulario de
     catalogación, sin pantalla de administración dedicada. Backend:
     `firstOrCreate(['nombre' => ...])` en `LibroController::idsPorNombre()`
     al guardar el libro — no hay `store()` propio en
     `CatalogoLibroController`, solo `index()`. Frontend:
     `MultiSelectCombobox.vue` (autocompleta contra `GET /autores|
     /categorias|/carreras` + opción "crear ‹texto›" si no matchea nada).
   - **Admin-only (`EstadoLibroPersonalizado`, `Ubicacion`)**: se crean
     únicamente desde `AdministracionView.vue` (`POST /estados-libro-
     personalizados`, `POST /ubicaciones`, ambos con middleware `admin`);
     el resto del staff solo los lee vía `GET` y los elige en un `<select>`
     — no hay "escribe y crea" en el formulario de catalogación para estos
     dos. `EstadoLibroPersonalizado` además tiene baja lógica (`activo`,
     mismo patrón que `Equipo`/`Staff`); `Ubicacion` no la necesita (no se
     ha pedido bloquear ubicaciones viejas).
   Si agregás un catálogo nuevo, elegí uno de los dos patrones a propósito
   y no un híbrido — mezclar "cualquiera crea" con "requiere admin" en el
   mismo catálogo es una fuente de confusión de permisos.

## Deuda técnica conocida (no asumir que ya se resolvió sin verificar el código)

- **`Prestamo.libro_titulo` ya NO es texto libre para libros del catálogo**
  (resuelto): `PrestamoController::store()` exige `codigo_barras`, busca el
  `Ejemplar` real (no `Libro` directamente, desde el split del 2026-08-15 —
  ver Gotchas), valida `disponible` + `estado_proceso === 'en_estante'` (409
  si ya está prestado/reservado por otra persona) y guarda `ejemplar_id` (FK
  real) + copia de `libro_titulo`/`codigo_barras` (con "(Copia N)" si
  corresponde). Al devolver, libera el ejemplar buscándolo por
  `ejemplar_id`. **Los equipos** (`tipo_item = audifonos|notebook|
  cargador`) **también están resueltos** — ya no son texto libre: `Equipo`
  es un modelo real (`codigo_inventario` único — nombre legible tipo
  "Notebook 01" — y `codigo_barras` único — lo que realmente se escanea al
  prestar, ver Gotchas 2026-08-16 —, `disponible`, `activo`), con el mismo
  chequeo doble que `Ejemplar` (`disponible` + `activo`, ver Gotchas). El
  gap de "sin múltiples copias del mismo título" que existía acá **se
  cerró el 2026-08-15** con el modelo `Ejemplar` — ver Gotchas para el
  detalle del split.
- **Constancia de No Multa: ya tiene autoservicio en el portal (resuelto
  2026-08-16)** — `UsuariosView.vue` (staff) tiene el botón "Constancia de
  No Multa" por usuario (bloqueado con un toast si tiene multa pendiente);
  `PortalHomeView.vue` (portal virtual) tiene el mismo botón para que el
  propio usuario la descargue sin pasar por mesón, vía `GET /mi/multas` +
  `GET /mi/configuracion` (`PortalController::misMultas()`, acotado al
  usuario autenticado — no recibe RUT por parámetro, así no se puede
  consultar la deuda de otra persona). Ambos flujos reutilizan el mismo
  `utils/constancia.ts` (`generarConstanciaNoMulta`). Verificado
  manualmente el 2026-08-16 en los dos lados: bloqueo con multa pendiente,
  descarga sin errores de consola sin deuda.
- **Sí hay tests automatizados** (`backend/tests/Feature/`, 30 archivos:
  `AuthTest`, `CascadaRestrictTest`, `CatalogoLibroTest`,
  `ConfiguracionInstitucionalTest`, `EjemplarCambioMasivoTest`,
  `EntradaTest`, `EnumCheckConstraintsTest`, `EquipoPrestamoTest`,
  `EstadoLibroPersonalizadoTest`, `LibroCatalogacionTest`,
  `LibroEstadoProcesoTest`, `LibroHistorialTest`, `MiddlewareTest`,
  `MultasPendientesTest`, `NotificacionesTest`, `PortalEntradaTest`,
  `PortalMisMultasTest`, `PortalReservaLibroTest`, `PortalReservaTest`,
  `PrestamoConcurrenciaTest`, `PrestamoLibroTest`, `PrestamoMultaTest`,
  `ReporteResumenLibrosSalasTest`, `ReservaLibroColaTest`,
  `ReservaLibroTest`, `SalaConfirmacionAsistenciaTest`,
  `SalaDevolucionTest`, `SalaReservaTest`, `StaffAtribucionTest`,
  `TipoMaterialTest`, `UsuarioAuthTest`), corren contra una DB Postgres
  dedicada (`biblioteca_test`, ver `docker-entrypoint.sh`) con
  `docker compose exec backend php artisan test` — 197 tests al
  2026-08-19. La catalogación de libros, el split Libro/Ejemplar, el
  cambio masivo de estado y el flujo completo de confirmación de
  asistencia de sala ya tienen cobertura completa. **Ojo**: hasta
  2026-08-19 `phpunit.xml` declaraba un testsuite "Unit" apuntando a
  `tests/Unit`, carpeta que ya no existe desde la limpieza del scaffold
  del 2026-08-13 (se fue junto con `ExampleTest.php`, pero nadie sacó la
  referencia) — `php artisan test` sin argumentos fallaba de entrada con
  "Test directory not found", y solo `--testsuite=Feature` funcionaba. Se
  sacó el bloque `<testsuite name="Unit">` de `phpunit.xml`. Si en algún
  momento se agregan tests unitarios reales, crear `tests/Unit/` primero
  y recién ahí volver a declarar ese testsuite.
- **Multas: aviso + vista consolidada, pero sin bloqueo duro** —
  `Prestamo.multa_monto`/`multa_estado` se calculan y guardan por préstamo
  individual al momento de `devolver()` (ver Gotchas). Ya existe:
  `UsuarioController::porRut()` devuelve `multas_pendientes` (cantidad +
  monto) y `PrestamoView.vue` muestra un aviso ámbar no bloqueante al crear
  un préstamo si el usuario tiene deuda; `GET /reportes/multas-pendientes`
  + `MultasPendientesView.vue` consolidan la deuda por usuario cruzando
  todos los préstamos. Lo que sigue sin existir es un **bloqueo duro**
  (rechazar el préstamo si hay multa pendiente) — es una decisión de
  alcance deliberada, no un bug, confirmada explícitamente con el usuario.
  **Actualización (2026-08-13)**: `multa_pagada_por` ya no es texto libre,
  ver `multa_pagada_por_staff_id` en la entrada de atribución de staff más
  abajo.
- **`Ejemplar`: dos ejes de estado independientes** (antes vivía en `Libro`,
  se movió con el split del 2026-08-15, ver Gotchas) — `disponible`
  (boolean, circulación: ¿está prestado/reservado ahora mismo?) y
  `estado_proceso` (string: `inventario` | `procesos_tecnicos` |
  `por_colocar` | `en_estante` | `estanteria_auxiliar` | `de_baja` |
  `coleccion_movil` | `personalizado` — los dos últimos agregados el
  2026-08-15). Un ejemplar solo es prestable/reservable si
  `estado_proceso === 'en_estante'` **y** `disponible === true` — no
  colapses estos dos campos en uno ni le des a `disponible` significados de
  estado físico, rompería `PrestamoController`/`ReservaLibroController`.
  Lo que sigue derivándose por query en vez de guardarse como columna:
  contadores de préstamos históricos/última fecha por ejemplar (ya hay una
  vista dedicada para verlos on-demand, `HistorialPrestamosLibroView.vue` +
  `LibroController::historial()`, pero no se cachean como columna aparte).
- **Credenciales de Postgres hardcodeadas** en `docker-compose.yml`
  (`biblioteca`/`biblioteca`) — aceptable para desarrollo local, es a
  propósito y no se toca. Para un despliegue real usar
  `docker-compose.prod.yml` (ver Gotchas), que toma las credenciales de
  variables de entorno en vez de hardcodearlas.
- **`PortalController` concentra varias responsabilidades** (estado/aforo,
  entrada/salida, catálogo, salas y reservas del usuario). Si crece más,
  conviene separar por dominio en vez de agregar más métodos ahí.

## Gotchas ya resueltos (no los reintroduzcas)

- **Ya no existe `app-overlay/`** (restructurado 2026-07-17): antes
  `docker-entrypoint.sh` corría `composer create-project` **dentro del
  contenedor ya arrancado**, guardaba el Laravel real en un volumen
  (`laravel_app`) y copiaba encima `app-overlay/` (nuestro código) en cada
  boot — la imagen nunca contenía un Laravel completo, solo esa carpeta. Se
  cambió a un Dockerfile normal: `composer install` corre en tiempo de
  **build** (capa cacheada — solo se reinstala si `composer.json`/
  `composer.lock` cambian) y `COPY . .` copia el proyecto completo, ya
  armado, directo a la imagen. `backend/` es ahora un Laravel real y
  autocontenido — no hay overlay que aplicar, no hay volumen `laravel_app`
  (se quitó de `docker-compose.yml`), y el primer arranque ya no depende de
  red para instalar nada. No reintroduzcas el patrón de "instalar en el
  primer boot dentro de un volumen" — si necesitás regenerar el esqueleto
  de Laravel desde cero por algún motivo, hacelo en una imagen descartable
  y fusionalo a mano como se hizo esta vez, no en el entrypoint de cada
  arranque.
  **Consecuencia práctica para el día a día**: antes, un cambio en
  `backend/` (Controller, Model, config, etc.) se veía con solo
  `docker compose restart backend` / `docker compose up` de nuevo, porque
  el entrypoint reaplicaba el overlay en cada arranque. **Ahora no** — el
  código está horneado en la imagen, así que hace falta
  `docker compose up --build backend` (o `--build` para todo el stack)
  para que un cambio de backend se refleje. `docker compose up` a secas
  solo levanta la imagen ya construida (o la construye si nunca existió),
  no recoge cambios de código nuevos. El frontend no cambia — sigue con
  hot-reload de Vite vía bind-mount, nunca necesitó rebuild.
- **Composer audit block**: Composer 2.8+ bloquea instalaciones por
  advisories de seguridad por defecto. La imagen define
  `COMPOSER_NO_AUDIT=1`, que es lo que realmente desactiva el audit para
  `composer install` (el flag `--no-audit` **no existe** para `install`,
  solo para `create-project`/`require` — no lo agregues a la línea de
  `composer install` del Dockerfile, falla con "option does not exist"). No
  quites la variable de entorno o el build puede volver a bloquearse.
- **CSRF "token mismatch" en el login**: `bootstrap/app.php` NO llama
  `$middleware->statefulApi()`. La auth es 100% Bearer token (Sanctum
  Personal Access Tokens), sin cookies de sesión — si se reactiva
  `statefulApi()` sin implementar el flujo de cookie CSRF completo en el
  frontend, el login vuelve a romperse.
- **Sin rate limiting en login**: ya se agregó `throttle:6,1` en las rutas
  `POST /auth/login` y `POST /auth/usuario/login` (`routes/api.php`) — más
  estricto a propósito que el límite general, no lo quites.
  **Actualización (2026-08-13)**: además del throttle de login, ya existe un
  límite global de 60 req/min (por usuario autenticado o IP) en el resto de
  la API vía `RateLimiter::for('api', ...)` en `AppServiceProvider::boot()` +
  `$middleware->throttleApi()` en `bootstrap/app.php` — antes solo las dos
  rutas de login tenían protección, el resto de las ~40 rutas autenticadas no
  tenía ninguna.
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
  `ReservaSalaService::participanteConReservaSolapada()` (busca si alguno de
  los RUT ya tiene una reserva con horario solapado ese día, en cualquier
  sala) en `SalaController::storeReserva` y `PortalController::reservarSala`.
  No reintroduzcas la validación sin este chequeo cruzado entre salas.
  **Actualización (2026-07-17)**: `Reserva.ruts` dejó de ser un array JSON —
  ahora es una tabla relacional `reserva_participantes`
  (`Reserva::participantes()`, `belongsToMany(Usuario::class)`), y
  `participanteConReservaSolapada()` ya no usa `whereJsonContains` (era una
  query por cada RUT del array) sino una sola query relacional. Si tocás
  esa función, no reintroduzcas el patrón JSON.
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
- **Loop infinito de `GET /auth/me` cuando el token queda stale** (ej. tras
  `mockup:datos --fresh`, que borra `staff` y revoca los tokens de sesiones
  ya abiertas en el navegador): `auth.validar()`/`usuarioAuth.validar()`
  detectaban el 401 pero solo devolvían `false`, sin limpiar
  `token`/`staff`/localStorage. El guard del router entonces rebotaba sin
  fin entre `login` (ve `auth.token` truthy → redirige a dashboard) y
  `dashboard` (`validar()` falla → redirige a login), disparando un
  `GET /auth/me` en cada vuelta. El interceptor 401 de `api.ts` no lo
  evitaba porque su propia guarda (`!pathname.startsWith('/login')`) lo
  desactiva justo en ese caso. Ya se corrigió: `validar()` limpia su propio
  estado (token/usuario/localStorage) al recibir un 401, cortando el
  ping-pong en el origen. No le quites esa limpieza a `validar()` ni asumas
  que basta con devolver `false`.
- **Multa por atraso al devolver un préstamo**: no existía ningún cálculo de
  multa — se agregó `config/multas.php` (tarifa/gracia/tope) +
  `App\Services\MultaService::calcular()`, llamado desde
  `PrestamoController::devolver()`. Solo aplica a `tipo_item === 'libro'`
  (los equipos no tienen `fecha_devolucion`, nunca quedan atrasados). Nuevo
  endpoint `PATCH /prestamos/{prestamo}/multa/pagar` para marcarla pagada.
  **Gotcha real encontrado en verificación manual**: Laravel 12 usa Carbon 3,
  cuyo `diffInDays()` devuelve un **float** con fracción de día (Carbon 2
  truncaba a entero) — calcular la multa como
  `$prestamo->fecha_devolucion->diffInDays($ahora) * monto_dia` sin más
  prorratea la multa por horas en vez de cobrar por día completo (ej. $1.227
  en vez de $1.200 para 4 días y 2 horas de atraso). Hay que forzar
  `(int) floor(...)` antes de multiplicar — ver `MultaService::calcular()` y
  el test de regresión `test_multa_no_se_prorratea_por_fraccion_de_dia` en
  `PrestamoMultaTest.php`. Si tocas ese método, no le quites el `floor()`.
- **Préstamos de equipo (audífonos/notebooks) sin ningún control**: antes
  `libro_titulo` era texto libre para `tipo_item !== 'libro'` — el mismo
  código (ej. "AUD-003") se podía prestar a dos personas en paralelo, nada
  lo impedía ni en frontend ni en backend. Ya se agregó el modelo `Equipo`
  (`codigo_inventario` único, `disponible`, `activo`) — `activo` es un eje
  independiente de `disponible`, igual que `Libro.estado_proceso`: un
  equipo dado de baja (`activo = false`) no es prestable aunque esté
  `disponible`, y no se puede dar de baja un equipo actualmente prestado
  (`EquipoController::cambiarActivo`, 409 si `! disponible`).
  `PrestamoController::store()` ahora busca el `Equipo` por
  `codigo_inventario` + `tipo`, igual que hace con `Libro` por
  `codigo_barras`. No reintroduzcas el flujo de texto libre sin este
  chequeo.
- **Multas pendientes sin ninguna señal en el sistema**: un usuario con
  deuda por atraso podía seguir pidiendo préstamos nuevos sin que nadie se
  enterara, y no había forma de ver cuánto debía cada usuario sin revisar
  préstamo por préstamo. Ya se agregó (a propósito **sin bloqueo duro**,
  ver Deuda técnica): `UsuarioController::porRut()` devuelve
  `multas_pendientes` (cantidad + monto, vía `Usuario::prestamos()`),
  mostrado como aviso ámbar no bloqueante en `PrestamoView.vue`; y
  `GET /reportes/multas-pendientes` (`ReporteController::multasPendientes`)
  agrupa por usuario para `MultasPendientesView.vue`. No agregues un
  bloqueo duro sin confirmarlo antes — fue una decisión explícita, no un
  olvido.
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
- **Salas ya no se agrupan por piso** (2026-08-13): antes había 25 logias
  repartidas artificialmente en `piso = '1er Piso' | '2do Piso'`, sin
  corresponder a la distribución real de la biblioteca — se sacó la
  columna `piso` de la tabla `salas` (migración
  `2024_01_02_000013_drop_piso_from_salas_table`, `down()` la restaura si
  hace falta) y el filtro por piso de `SalasView.vue`. El inventario real
  de salas ahora es: 15 logias de estudio (`tipo = 'logia'`, con
  `codigo_barras` Horizon como antes) + 3 salas con nombre propio y
  `tipo = 'sala'` sin `codigo_barras` (no pasan por
  `ReservaSalaService::escanearLogia()`, que sigue filtrando
  `where('tipo', 'logia')`): **Sala de Seminarios**, **Sala de Postgrado**
  y **Sala GACI** (apoyo a la inclusión). Total 18 filas en `salas`, todas
  reservables por el mismo flujo de bloques horarios de 2h. Si necesitás
  volver a distinguir ubicación física, agregá un campo nuevo (ej.
  `ubicacion`) en vez de reintroducir `piso` con semántica de "1er/2do
  piso" — no correspondía a la realidad y por eso se sacó.
- **Unicidad de `codigo_barras` en `libros`** (verificado 2026-08-13, no
  era un gap real): ya estaba cubierta en dos capas —
  `libros.codigo_barras` es `unique()` a nivel de columna desde la
  migración original (`create_libros_table`), y
  `LibroController::reglasCatalogacion()` valida
  `unique:libros,codigo_barras,{id},id` tanto en `store()` como en
  `update()` (ignorando el propio registro al editar), devolviendo 422 con
  mensaje de validación en vez de un error 500 de constraint de DB. Lo que
  faltaba era cobertura de test — se agregó
  `tests/Feature/LibroCatalogacionTest.php` (crear con código repetido,
  actualizar al código de otro, actualizar manteniendo el propio código, y
  el caso límite de bypass de validación a nivel de Eloquent). No le quites
  la regla `unique` de `reglasCatalogacion()` ni asumas que hace falta
  agregarla — ya estaba ahí.
- **Condición de carrera real en préstamo/reserva de libro** (2026-08-13):
  `PrestamoController::store()` y `ReservaLibroController::store()` leían
  `Libro`/`Equipo.disponible`, decidían, y recién después escribían, sin
  ninguna transacción ni lock (`grep -rn "DB::transaction\|lockForUpdate"
  app/` daba cero resultados en todo el proyecto). Dos requests concurrentes
  sobre el mismo `codigo_barras`/`codigo_inventario` podían ambas pasar el
  chequeo `disponible` antes de que la primera escribiera, resultando en
  doble préstamo del mismo ejemplar único. Ya se corrigió: todo el ciclo
  lectura→decisión→escritura de `store()` (y `devolver()`/`cancelar()`, por
  atomicidad) va dentro de `DB::transaction()` con `->lockForUpdate()` sobre
  la fila de `Libro`/`Equipo` — el lock **solo tiene efecto dentro de una
  transacción explícita**, no lo separes del closure. Test de regresión:
  `PrestamoConcurrenciaTest.php` (verifica con `DB::listen()` que la query
  real incluye `FOR UPDATE`, no depende de timing real). No reintroduzcas un
  `->first()` suelto seguido de `->update()` en estos flujos.
- **Enums simulados sin restricción real en la base de datos** (2026-08-13):
  `usuarios.tipo`, `prestamos.estado/tipo_item/multa_estado`,
  `libros.tipo_material/estado_proceso`, `reservas_libro.estado` y
  `equipos.tipo` eran `string` planos — la única validación era la regla
  `in:` de cada controller, así que cualquier inserción directa (seeder,
  tinker, un futuro import de Horizon) podía dejar un valor inválido. Ya se
  agregó un `CHECK CONSTRAINT` de Postgres por columna
  (`2024_01_03_000001_add_check_constraints_to_enum_columns.php`, vía
  `DB::statement()` — Postgres no tiene `->check()` nativo en el Schema
  Builder de Laravel). Los valores incluyen `prestamos.estado = 'atrasado'`
  y `reservas_libro.estado = 'retirado'` aunque hoy ningún controller los
  escriba (solo el seeder) — si agregás un valor nuevo a una regla `in:` de
  un controller, actualizá también el CHECK o `mockup:datos` (u otro insert
  directo) puede empezar a fallar. **Excluido a propósito**: `usuarios.sexo`
  no tiene CHECK — no es un enum simulado, no tiene regla `in:` en ningún
  controller, `ReporteController` lo puebla dinámicamente vía `distinct()`.
  Test: `EnumCheckConstraintsTest.php`.
- **Cascadas de borrado inconsistentes entre tablas equivalentes**
  (2026-08-13): `prestamos.usuario_id` era CASCADE (borrar un `Usuario`
  borraba su historial financiero de multas), `entradas.usuario_id` era
  CASCADE (borraba asistencia), pero `prestamos.libro_id`/`equipo_id` eran
  `nullOnDelete`, y `reservas_libro.libro_id` era CASCADE mientras
  `prestamos.libro_id` era SET NULL para el mismo tipo de relación — sin
  ningún criterio uniforme (y sin ningún endpoint que borrara `Usuario`/
  `Libro` hoy, así que era un riesgo latente, no explotado). Ya se unificó a
  **RESTRICT** en todas las FK de historial/circulación
  (`2024_01_03_000003_restrict_delete_on_historial_foreign_keys.php`):
  `prestamos.usuario_id/libro_id/equipo_id`, `entradas.usuario_id`,
  `reservas_libro.usuario_id/libro_id`. La baja lógica ya existe en el padre
  (`usuarios.activo`, `equipos.activo`, `libros.estado_proceso='de_baja'`,
  `staff.activo`) — RESTRICT bloquea el hard delete a nivel de Postgres
  incluso ante un `DELETE` manual en `psql` o un import mal escrito, no
  reintroduzcas CASCADE/SET NULL en estas columnas. **Consecuencia
  encontrada al aplicar esto**: `SeedMockupData::handle()` con `--fresh`
  borraba `libros`/`equipos`/`usuarios` **antes** que `prestamos`, lo que
  ahora viola el RESTRICT — el orden de los `DB::table(...)->delete()` se
  corrigió (hijos antes que padres: `reservas_libro` → `prestamos` →
  `reserva_participantes` → `reservas` → `entradas` → `libros` → `equipos` →
  `usuarios` → `salas` → `staff`). Si agregás una tabla nueva con FK
  RESTRICT hacia otra que el seeder borra, actualizá ese orden. Índices
  faltantes en FK (Postgres no los crea automáticamente, a diferencia de
  MySQL/InnoDB) también se agregaron en el mismo lote
  (`2024_01_03_000002_add_missing_indexes_to_foreign_keys.php`):
  `prestamos.libro_id/equipo_id/fecha_prestamo/fecha_devolucion`,
  `reservas_libro.libro_id`, `codigo_acceso.generado_por`. Test:
  `CascadaRestrictTest.php`.
- **`prestado_por`/`devuelto_por`/`multa_pagada_por` eran texto libre
  tipeado por el staff** (2026-08-13): el propio staff autenticado escribía
  a mano quién prestó/devolvió/cobró (con un datalist de autocompletar en
  `PrestamoView.vue`/`ListadoPrestamosView.vue`) — no eran FK, y aunque lo
  fueran, el dato lo seguía escribiendo el cliente (podía tipear el nombre
  de cualquier otro staff). Ya se agregaron `prestado_por_staff_id`,
  `devuelto_por_staff_id`, `multa_pagada_por_staff_id` (FK reales a `staff`,
  `nullOnDelete`) en `PrestamoController`, estampados automáticamente desde
  `$request->user()` — **ya no se piden en el body**, un valor de
  `prestado_por` enviado en el request se ignora. Las columnas string viejas
  se mantienen como snapshot legible del nombre en ese momento (no como
  fuente de verdad). Se quitaron los inputs de texto y el `<datalist
  id="staff-nombres">` correspondientes de `PrestamoView.vue`/
  `ListadoPrestamosView.vue` (ojo: `useStaffNombres.ts` sigue vivo, lo sigue
  usando `SalasView.vue` para "registrado por" en el escaneo de logias — no
  es lo mismo). No reintroduzcas un campo de texto libre para esto. Tests:
  `StaffAtribucionTest.php`, más el ajuste en `PrestamoMultaTest.php`.
- **`staff` sin mecanismo de baja lógica** (2026-08-13): `usuarios.activo` y
  `equipos.activo` ya existían, `staff` era la única entidad "padre" sin
  ninguno. Ya se agregó `staff.activo` (default `true`) y
  `AuthController::login` ya rechaza con 422 si `activo = false`, mismo
  patrón que `UsuarioAuthController::login` usa para `usuarios.activo`.
- **Producción real: timezone fijo en UTC y sin plantilla de despliegue**
  (2026-08-13): `config/app.php` tenía `'timezone' => 'UTC'` hardcodeado (no
  venía de `.env`), sin conversión a hora de Chile documentada en ningún
  lado. Ya se cambió a `env('APP_TIMEZONE', 'America/Punta_Arenas')` +
  `APP_TIMEZONE` en `.env.example`. También se agregaron
  `backend/.env.production.example` (plantilla con placeholders
  `__REEMPLAZAR__`, `APP_DEBUG=false`, `DB_SSLMODE=require`) y
  `docker-compose.prod.yml` (sin bind mounts, sin credenciales hardcodeadas,
  sin exponer el puerto 5432 al host) — ninguno de los dos tiene valores
  reales, son plantillas de referencia para cuando haya un despliegue real.
  No los uses tal cual sin completar las variables de entorno reales.
- **Scaffold default de `laravel new` sin uso real, limpiado** (2026-08-13):
  el backend es API-only, pero sobrevivían archivos del scaffold web por
  defecto de Laravel, desconectados de la app real (nada los importaba ni
  los servía — no hay Vite configurado en `backend/` para compilarlos):
  `resources/views/welcome.blade.php`, `resources/css/app.css`,
  `resources/js/app.js`+`bootstrap.js`, `public/favicon.ico` — ya se
  eliminaron (la carpeta `resources/` completa desapareció, quedaba vacía).
  `routes/web.php` ya no referencia la vista borrada, devuelve un JSON
  simple en `/`. También se eliminó `App\Models\User` +
  `database/factories/UserFactory.php` + las tablas `users`/
  `password_reset_tokens`/`sessions` (migración
  `2024_01_03_000007_drop_default_laravel_auth_tables.php`) — era el guard
  `web`/sesión por defecto, nunca usado (la auth real es 100% Bearer token
  vía Sanctum sobre los guards `staff`/`usuario`, `SESSION_DRIVER` siempre
  fue `file`, no `database`). `config/auth.php` quedó con el `provider
  'users'` sin modelo por defecto (`env('AUTH_MODEL')`, sin fallback a
  `User::class`) — no le agregues un modelo ahí salvo que reactives
  sesión/guard `web` de verdad. También se sacaron `ExampleTest.php`
  (Feature y Unit, boilerplate default sin lógica de negocio) y el
  `faviconV2.png` huérfano de la raíz del repo (sin ninguna referencia, el
  favicon real es `frontend/public/favicon.png`). `frontend/tsconfig.tsbuildinfo`
  (artefacto de caché de `vue-tsc`) se sacó del tracking de git y se agregó
  `*.tsbuildinfo` al `.gitignore` — se regenera solo, no debería
  versionarse.
- **Reservar un libro ocupado devolvía 409 sin alternativa — ya hay cola de
  espera** (2026-08-13): antes `ReservaLibroController::store()` rechazaba
  de plano reservar un libro que ya estaba prestado/reservado — que es
  justo el caso más común de querer reservar un libro. Ya se agregó un
  estado nuevo `reservas_libro.estado = 'en_cola'` (CHECK constraint
  actualizado en
  `2024_01_04_000001_add_en_cola_estado_to_reservas_libro_table.php`, que
  también volvió `fecha_retiro` nullable — una fila `en_cola` todavía no
  tiene fecha límite). Toda la lógica compartida vive en
  `App\Services\ReservaLibroService`:
  - `reservarOEncolar()`: si el libro está disponible, reserva como antes
    (`estado = 'pendiente'`); si no, crea la fila `en_cola` (FIFO por `id`,
    **no** por `created_at` — dos filas creadas en el mismo segundo
    empatarían el orden con `created_at`, el autoincremental no tiene esa
    ambigüedad).
  - `liberarLibro()`: se llama siempre que un libro pasa a estar disponible
    (`PrestamoController::devolver()` al devolver un préstamo, `cancelar()`
    al cancelar una reserva `'pendiente'`) — en vez de simplemente poner
    `disponible = true`, primero busca si hay alguien `en_cola` y lo
    promueve a `pendiente` con una `fecha_retiro` nueva
    (`config('reservas_libro.dias_para_retirar')`, calculada en ese
    momento, no elegida por nadie). El libro **sigue** `disponible = false`
    si hubo promoción — recién queda realmente libre cuando la cola está
    vacía. Cancelar una reserva `'en_cola'` (alguien se arrepiente de
    esperar) NO dispara `liberarLibro()` — nunca tuvo el libro apartado, no
    afecta a nadie más.
  - No reintroduzcas el 409 duro para "libro no disponible" en
    `reservarOEncolar()` — el 409 sigue existiendo, pero solo para
    `estado_proceso !== 'en_estante'` o para quien ya tiene una reserva/
    lugar en cola activo sobre el mismo libro.
  - `ReservaLibroController::store()` (staff) ya no exige `fecha_reserva`/
    `fecha_retiro` (pasaron a `nullable`) — si el staff no las manda, se
    calculan igual que en el portal virtual.
  Tests: `ReservaLibroColaTest.php` (FIFO con 3 personas, promoción al
  devolver/cancelar, cola no afecta disponibilidad al cancelarse a sí
  misma).
- **Autoservicio de reservas de libro desde el portal virtual** (2026-08-13):
  antes un usuario del portal virtual podía ver el catálogo y reservar una
  **sala** por su cuenta, pero para pedir un **libro** tenía que ir a
  mesón — `/reservas-libro` solo existía en el grupo de rutas de `staff`.
  Ya se agregó `PortalReservaLibroController` (nuevo, no se metió en
  `PortalController` a propósito — ver Deuda técnica) con
  `GET/POST /mi/reservas-libro` y
  `PATCH /mi/reservas-libro/{reservaLibro}/cancelar`, usando el mismo
  `ReservaLibroService` que el staff — un usuario del portal virtual
  reserva o se une a la cola de espera sin elegir fechas (se calculan
  solas). El chequeo de dueño
  (`$reservaLibro->usuario_id !== $request->user()->id` → 403) sigue el
  mismo patrón que `PortalController::cancelarReservaSala()`. Frontend:
  `PortalCatalogoView.vue` (portal virtual) ahora tiene botón "Reservar"/
  "Unirme a la espera" por libro y una sección "Mis reservas" con la
  posición en la cola; `PrestamoView.vue` (staff) también soporta unirse a
  la cola desde el mismo formulario de reserva (el botón cambia a "Unirse
  a la Lista de Espera" cuando el libro no está disponible). Tests:
  `PortalReservaLibroTest.php`.
- **Split `Libro`/`Ejemplar` — cierra el gap #2 del checklist de Horizon**
  (2026-08-15): antes 1 fila `Libro` = 1 copia física (un solo
  `disponible`/`estado_proceso`/`codigo_barras` por título, sin soportar
  múltiples copias). Se separó en dos tablas: `libros` (obra —
  título/ISBN/clasificación/colección/editorial/año/tipo_material/notas,
  más `autores`/`categorias`/`carreras` N:M vía pivotes `libro_autor`/
  `libro_categoria`/`libro_carrera`) y `ejemplares` (copia física —
  `libro_id`, `numero_copia`, `codigo_barras` propio y único,
  `disponible`, `estado_proceso`, `estado_personalizado_id`,
  `ubicacion_id`, `volumen`, `precio`, `fecha_inventario`). La migración de
  backfill (`2026_08_14_000014_backfill_ejemplares_y_pivots_desde_libros`)
  usa `DB::table()` crudo (no Eloquent) para no depender de los modelos
  reescritos en el mismo lote, y crea un `Ejemplar` con `numero_copia = 1`
  por cada `Libro` preexistente antes de dropear las columnas físicas de
  `libros` — es **irreversible sin pérdida de información** una vez que
  existan libros con más de 1 copia (documentado en el propio archivo de
  migración). `Prestamo`/`ReservaLibro` cambiaron de `libro_id` a
  `ejemplar_id` como FK real; `libro_titulo` (snapshot de texto) se
  mantiene igual, ahora con "(Copia N)" agregado cuando corresponde
  (`Ejemplar::tituloConCopia()`). `PrestamoController`/
  `ReservaLibroController`/`ReservaLibroService` ahora buscan y bloquean
  (`lockForUpdate()`) sobre `Ejemplar`, no sobre `Libro`. Si necesitás
  tocar disponibilidad/estado de una copia, es en `Ejemplar` — `Libro` ya
  no tiene esos campos. `Autor`/`Categoria`/`Carrera` son catálogos
  "escribe y crea" (ver convención 8); `LibroController::store()` sigue
  creando el primer `Ejemplar` en la misma transacción que el `Libro`, así
  que catalogar un libro nuevo se siente igual que antes desde la UI aunque
  por debajo ya sean dos tablas.
- **Estados de ejemplar ampliados + catálogo de estados personalizados**
  (2026-08-15): a los 6 valores fijos de `estado_proceso` se sumaron
  `coleccion_movil` (fijo, sin tabla propia) y `personalizado` (requiere
  `estado_personalizado_id`, FK a `estados_libro_personalizados` —
  catálogo administrable, ver convención 8, con baja lógica `activo` igual
  que `Equipo`/`Staff`; un estado ya usado por algún `Ejemplar` no se
  puede borrar, solo desactivar). El CHECK constraint de
  `ejemplares.estado_proceso` se actualizó para incluir los 2 valores
  nuevos — si agregás un valor más, actualizá el CHECK o `mockup:datos`
  puede fallar (mismo patrón que el resto de los enums simulados, ver
  gotcha de CHECK constraints más arriba). Tests: `EstadoLibroPersonalizadoTest.php`.
- **Cambio masivo de estado de ejemplares, con confirmación seria**
  (2026-08-15): `EjemplarController::cambioMasivoPreview`/
  `cambioMasivoEjecutar` (`CambioMasivoEstadoView.vue`, solo admin) cambian
  el `estado_proceso` de muchos ejemplares a la vez filtrando por
  `ubicacion_id`/`estado_proceso_actual`/`tipo_material`/`categoria_id`/`q`
  — `validarFiltro()` exige al menos un criterio (`abort(422, ...)`), para
  que no se pueda mandar un cambio masivo vacío que afecte a todos los
  ejemplares del sistema. `preview` no escribe nada, solo cuenta y muestra
  una muestra de 10; `ejecutar` vuelve a aplicar el mismo filtro
  server-side (no confía en una lista de IDs que el cliente pudo cachear
  stale desde el preview) y, dentro de una única `DB::transaction()`,
  actualiza cada ejemplar + escribe una fila en `ejemplar_estado_historial`
  por cada uno, todas con el mismo `lote_id` (UUID) para poder ver después
  "qué se cambió junto en esta operación" desde `HistorialEstadoLibroView.vue`.
  Igual que en el cambio individual, un ejemplar `de_baja` con
  `disponible = false` (prestado/reservado) se excluye del lote en vez de
  fallar la operación completa por uno solo ocupado. El frontend exige
  tipear la palabra "CONFIRMAR" en un modal antes de habilitar el botón de
  ejecutar — no es un simple `confirm()` de un click. Tests:
  `EjemplarCambioMasivoTest.php`.
- **Generador de código de barras secuencial** (2026-08-15):
  `GET /ejemplares/siguiente-codigo-barras` (solo admin) busca el mayor
  sufijo numérico entre códigos con patrón `UMAG######` y devuelve el
  siguiente, zero-padded a 6 dígitos (`UMAG000042`). Es solo una sugerencia
  para rellenar el input — el staff puede editarlo antes de guardar, y la
  regla `unique:ejemplares,codigo_barras` en `reglasCatalogacion()` sigue
  validando en el submit. No auto-asigna nada por su cuenta.
- **Historial de cambios de estado por ejemplar + historial de préstamos
  por libro** (2026-08-15), dos vistas nuevas y distintas — no las
  confundas: `EjemplarController::historialEstado()` +
  `HistorialEstadoLibroView.vue` lista cambios de `estado_proceso`
  (filtrable por `ejemplar_id`/`lote_id`/`staff_id`/`q`/rango de fecha),
  poblada por `EjemplarEstadoHistorial` cada vez que cambia el estado de un
  ejemplar (individual o masivo). `LibroController::historial()` +
  `HistorialPrestamosLibroView.vue` es sobre préstamos, no sobre estado:
  busca por título/código, agrupa por libro → cada ejemplar con su lista de
  préstamos (usuario, fechas) y un conteo total. Ninguno de los dos
  necesita columnas nuevas de conteo cacheado — ambos derivan de queries
  sobre las tablas existentes.
- **Reportes nuevos: Top de Libros y uso de logias por sala + heatmap**
  (2026-08-15): `ReporteController::resumen()` ahora soporta `tab=libros`
  (`porLibro`, agrupado por `libro_titulo` del préstamo — snapshot de
  texto, no la relación viva, así el ranking sigue correcto aunque el
  ejemplar prestado ya no exista o el libro haya cambiado de título) y,
  dentro de `tab=logias`, agrega `porSala` (reservas agrupadas por
  `sala.nombre`) y `porHoraPorSala` (mismo desglose que `porHora`, pero
  repetido POR CADA sala — para el heatmap "qué hora es la más pedida EN
  CADA logia", no solo el agregado de todas juntas). `porSala`/`porLibro`/
  `porHoraPorSala` vienen vacíos (`collect()`) fuera de su tab
  correspondiente — no tiene sentido calcularlos si no se van a mostrar.
  Frontend: `Heatmap.vue` (grid sala×hora, rampa de color secuencial de un
  solo hue, sin librería externa — sigue la skill `dataviz` del repo) +
  card "Sala más solicitada" en `ReportesView.vue`. Tests:
  `ReporteResumenLibrosSalasTest.php`.
- **Entrada "Visita"** (2026-08-15): tercera categoría de entrada externa,
  junto a "Externo" y "Convenio" — mismo flujo que `storeExterno()` pero
  con `entradas.es_visita = true` (columna nueva, mismo patrón booleano que
  `es_convenio`) para diferenciarla en reportería/UI (badge "Visita" en
  `EntradaView.vue`). Ruta `POST /entrada/visita` →
  `EntradaController::storeVisita()`.
- **Configuración institucional editable + Constancia de No Multa en PDF**
  (2026-08-15): antes no existía forma de emitir una constancia de que un
  usuario no tiene deuda con la biblioteca, y el nombre de quien firmaría
  ese tipo de documento no estaba en ningún lado del sistema. Se agregó
  `configuracion_institucional` (fila única `id=1`,
  `jefe_unidad_nombre`/`jefe_unidad_cargo`) +
  `ConfiguracionInstitucionalController::show()` (todo staff)/`actualizar()`
  (admin, editable desde `AdministracionView.vue`) — **no hardcodees** el
  nombre de la jefa de unidad en el frontend, siempre viene de
  `GET /configuracion`. `utils/constancia.ts`
  (`generarConstanciaNoMulta(usuario, configuracion)`) arma el PDF
  **enteramente en el navegador** con `jsPDF` (dependencia npm nueva, mismo
  patrón que `qrcode`/`exceljs` ya usados en el proyecto) — sin backend
  dedicado, sin logo institucional como imagen (el repo no tenía ninguno
  más allá de `favicon.png`, así que el encabezado es de texto, no una
  réplica pixel-perfect del documento con timbre y firma manuscrita). El
  botón en `UsuariosView.vue` llama primero a `GET /usuarios/rut/{rut}`
  (ya devuelve `multas_pendientes`) y **bloquea la generación con un toast
  de error** si `cantidad > 0` — verificado manualmente el 2026-08-15 con
  un usuario con multa pendiente (bloqueado) y uno sin deuda (PDF
  descargado correctamente, sin errores de consola). El mismo flujo ya
  tiene autoservicio desde el portal virtual también (ver entrada
  2026-08-16 más abajo). Tests: `ConfiguracionInstitucionalTest.php`.
  **Actualización de formato (2026-08-16)**: el encabezado pasó a imitar un
  membrete real — "UMAG" + "Universidad de Magallanes" a la izquierda,
  línea vertical divisoria, "Unidad de Gestión de Recursos Educativos" a la
  derecha (antes era texto suelto sin línea) — y el párrafo principal ya no
  es un solo `doc.text()` con una fuente uniforme: usa
  `dibujarParrafoConEstilos()` (helper nuevo en el mismo archivo) para
  mezclar negrita/subrayado por tramo dentro de una misma línea — nombre
  completo y RUT quedan subrayados, programa/carrera y fecha quedan en
  negrita. jsPDF no soporta texto enriquecido ni subrayado nativo: el
  helper posiciona cada palabra a mano (preservando espacios vía
  `split(/(\s+)/)`, no `split(' ')` — perder los espacios entre segmentos
  fue el bug real la primera vez que se escribió esto) y dibuja el
  subrayado como una sola línea continua por segmento (no una por palabra).
  Si agregás un campo nuevo con estilo mixto a este documento, reusá
  `dibujarParrafoConEstilos()`/`medirAnchoSegmentos()` en vez de volver a
  `doc.text()` plano.
  **Segunda pasada de formato (2026-08-16)**: "Don/Doña" pasó a "Don(a)"
  (una sola palabra en vez de dos separadas por barra). Se agregó el sello
  institucional real como imagen —
  `frontend/src/assets/sello-biblioteca.png` (recortado con ImageMagick al
  círculo visible, sin el padding transparente que traía el archivo
  original; el original vivía suelto en la raíz del repo, se movió a
  `assets/` como cualquier otro logo del proyecto, ver `logo-umag.png` en
  la misma carpeta) — centrado justo encima de la línea de firma. Como
  jsPDF necesita la imagen ya en base64 para `addImage()` (no acepta una
  URL de asset de Vite directamente), `generarConstanciaNoMulta()` pasó a
  ser **`async`** — hace `fetch()` del asset importado y lo convierte con
  `FileReader.readAsDataURL()` (`cargarImagenComoBase64()`, helper nuevo
  en el mismo archivo) antes de dibujar. **Los dos callers
  (`UsuariosView.vue`, `PortalHomeView.vue`) ahora hacen `await
  generarConstanciaNoMulta(...)`** — si agregás un caller nuevo (ej. algún
  día un reporte en PDF que reuse el sello), no te olvides del `await`, o
  el archivo se dispara a descargar sin el sello (la promesa de
  `addImage` corre en paralelo con el resto y probablemente pierda la
  carrera). El alto del sello en el PDF se calcula con
  `doc.getImageProperties()` (relación de aspecto real del PNG), no un
  valor fijo — no lo hardcodees si el asset cambia de tamaño.
  **Tercera pasada (mismo día)**: nombre completo y RUT pasaron de
  subrayado a **negrita** (`negrita: true` en vez de `subrayado: true` en
  esos dos segmentos, dentro de `generarConstanciaNoMulta()`) — el helper
  `dibujarParrafoConEstilos()` sigue soportando `subrayado` para quien lo
  necesite después, simplemente no se usa en este documento por ahora. El
  PNG del sello traía el interior del círculo con un gris sucio/parejo
  (textura de sello real escaneado) — se limpió con
  `magick sello-biblioteca.png -level 20%,90% sello-biblioteca.png`
  (empuja los grises claros a blanco sin aplanar el borde/las letras, que
  son mucho más oscuros) antes de guardarlo en `assets/`. Si se reemplaza
  el asset por uno nuevo con el mismo problema, este es el comando a
  repetir — no subas el PNG del sello directo desde el escáner/celular sin
  pasarlo por este ajuste.
- **Sala "GACI" renombrada a "AGACI"** (2026-08-15): migración de datos
  (`2026_08_14_000001_rename_gaci_sala_to_agaci`,
  `UPDATE salas SET nombre = REPLACE(nombre, 'GACI', 'AGACI') ...`, con
  `down()` que revierte) + `SeedMockupData.php` actualizado para que
  futuros `--fresh` ya generen "AGACI" directamente. Si ves "GACI" a secas
  en código o docs viejos, es el nombre anterior — el real ahora es
  "AGACI".
- **Multa por atraso: $300 → $15 por día** (2026-08-15): cambio de un solo
  valor en `config/multas.php` (`monto_dia`), sin tocar
  `MultaService::calcular()` ni el `floor()` de la nota anterior sobre
  Carbon 3 — si volvés a ajustar la tarifa, es ese archivo, nunca
  hardcodeado en el service.
- **Reserva de sala: alumnos solo pueden reservar para el día de hoy, y hay
  una ventana de 15 minutos para confirmar que llegaron** (2026-08-15):
  - `PortalController::reservarSala()` rechaza con 422 si
    `fecha !== now()->toDateString()` — el selector de fecha se sacó de
    `PortalSalasView.vue` (antes `ref`, ahora una constante `hoy`), ya no
    hay forma de que un alumno pida una sala para "mañana" o cualquier otro
    día. El staff (`SalaController::storeReserva`) NO tiene esta
    restricción — puede reservar para cualquier fecha, como antes.
  - `Reserva::plazoConfirmacion()` calcula el límite como
    `max(created_at, inicio_del_bloque) + 15 minutos` — si alguien reserva
    el bloque 14–16h a las 13:50, el plazo corre desde las 14:00 (inicio
    del bloque); si reserva a las 15:00 vía "Reservar ahora" dentro de ese
    mismo bloque, el plazo corre desde las 15:00 (el momento real de la
    reserva), no desde las 14:00 — así nunca nace con un plazo ya vencido.
    `estaVencidaSinConfirmar()` es `true` si sigue `estado === 'activa'`,
    sin `hora_prestamo_real`, y ya pasó ese plazo.
  - **Expiración perezosa, sin cron/scheduler**: no hay ningún job en
    background — `ReservaSalaService::liberarSiVencida()` se llama cada vez
    que se LEE una reserva (`SalaController::index`,
    `PortalController::salas()`, y dentro de
    `existeSolapamiento()`/`participanteConReservaSolapada()` antes de
    aceptar una reserva nueva sobre ese bloque) y, si está vencida, la
    marca `estado = 'no_show'` ahí mismo. Si agregás un lugar nuevo que lea
    reservas, llamá a este método antes de confiar en `estado`, o vas a
    mostrar/permitir cosas sobre una reserva que en teoría ya debería estar
    liberada.
  - **Menú de confirmación de asistencia para el staff**
    (`SalaController::confirmarLlegada`/`liberarReserva`,
    `PATCH /reservas/{reserva}/llegada` y `/liberar`): panel nuevo en
    `SalasView.vue` que lista las reservas activas sin confirmar, con
    cuenta regresiva en vivo (reloj reactivo, `setInterval` de 1s) y
    botones para confirmar llegada (marca `hora_prestamo_real`) o liberar
    manualmente antes de que se cumpla el plazo. `index()` ya no devuelve
    reservas `no_show` y expone `plazo_confirmacion`/
    `vencida_sin_confirmar` como atributos dinámicos por reserva.
  Tests: `SalaConfirmacionAsistenciaTest.php` (10 tests, usa
  `Carbon::setTestNow()` con `tearDown()` de reset — no timing real) +
  2 tests nuevos en `PortalReservaTest.php` para la restricción de fecha.
- **Bug reportado: la pantalla de "confirmaste tu asistencia" del QR
  desaparecía sola después de escanear** (2026-08-15): en
  `PortalEntradaView.vue`, `registrar()` volvía a `modo.value = 'menu'`
  automáticamente 2.5s después de mostrar el mensaje de éxito — el usuario
  alcanzaba a leer "confirmado" pero la pantalla saltaba sola al menú antes
  de que terminara de asimilarlo, y como el menú de esa vista también
  cambia de estado solo, se sentía como que "algo raro pasó". Se cambió el
  timeout para que en vez de resetear a `modo = 'menu'` (mismo componente,
  cambio de estado interno) haga `router.push({ name: 'portal-home' })` —
  navega de verdad a otra pantalla, comportamiento más predecible y menos
  confuso que un cambio de estado silencioso en el mismo componente.
- **Logins de staff y de usuarios finales se veían casi idénticos**
  (2026-08-15): ambos decían básicamente "Bienvenido"/"Iniciar sesión" sin
  distinguirse. Se diferenciaron los títulos: `LoginView.vue` (staff) dice
  "Administración" / "Panel para personal de biblioteca";
  `PortalLoginView.vue` dice "Inicio de Sesión Usuarios" / "Portal para
  estudiantes, docentes y funcionarios". `LoginV2View.vue` (variante
  secundaria detrás de un link "Versión 2", no es el flujo principal) no se
  tocó — no está en el mismo punto de comparación que generó la confusión.
- **RUT inválido en una reserva grupal de sala: no se sabía cuál de los
  varios RUT estaba mal** (2026-08-15): antes, si uno de los RUT de una
  reserva grupal (`SalasView.vue`/`PortalSalasView.vue`, 2 a 5 personas) no
  correspondía a un usuario registrado, el backend devolvía 422 con
  `errors: {"ruts.1": ["..."]}` pero el frontend solo mostraba un toast
  genérico ("datos inválidos") sin decir cuál de los inputs tenía el
  problema — había que adivinar. Ya se agregó `rutErrores` (mapa índice →
  mensaje) en ambas vistas: el `catch` del submit parsea las claves
  `ruts.N` de `error.response.data.errors`, marca en rojo (borde + mensaje
  debajo) exactamente el/los input(s) correspondientes, y el resto queda
  intacto. Se limpia por índice al reescribir ese campo (`onRutInput`) y
  por completo al reabrir el modal o reintentar el submit. Verificado
  manualmente con un RUT inválido, dos inválidos, y una mezcla
  válido+inválido — en los tres casos se marca exactamente el campo
  correcto, sin falsos positivos sobre el RUT válido.
- **Ubicación física de un ejemplar: de texto libre a catálogo
  administrable** (2026-08-15): antes `ejemplares.ubicacion` (antes en
  `libros.ubicacion`) era un `string` cualquiera tipeado a mano en
  catalogación y en el filtro de cambio masivo — sin autocompletar, sin
  forma de estandarizar valores ("Biblioteca Central" vs "biblioteca
  central" vs "Bibl. Central" convivían como strings distintos). Se agregó
  el modelo `Ubicacion` (catálogo admin-only, ver convención 8) +
  `ejemplares.ubicacion_id` (FK, `nullOnDelete`) reemplazando la columna de
  texto — la migración de backfill
  (`2026_08_15_000001_create_ubicaciones_table`) crea una `Ubicacion` por
  cada valor de texto distinto que ya existiera en `ejemplares.ubicacion`
  antes de dropear esa columna, y siembra "Biblioteca Central" como
  ejemplo si la tabla queda vacía. `CatalogacionLibrosView.vue` (ambos
  formularios: libro nuevo y agregar copia) y
  `CambioMasivoEstadoView.vue` (filtro) pasaron de `<input>` a `<select>`
  poblado desde `GET /ubicaciones`. No reintroduzcas un input de texto
  libre para esto.
- **Constancia de No Multa: autoservicio en el portal virtual**
  (2026-08-16): hasta acá el botón "Constancia de No Multa" solo existía en
  `UsuariosView.vue` (staff, buscando por RUT). Se agregó el mismo botón en
  `PortalHomeView.vue` para que el propio usuario la descargue sin pasar
  por mesón. Backend nuevo: `PortalController::misMultas()`
  (`GET /mi/multas`, dentro del guard `usuario`) — mismo cálculo que
  `UsuarioController::porRut()` pero **acotado a `$request->user()`**, sin
  recibir RUT por parámetro, para que un usuario del portal no pueda
  consultar la deuda de otro. También se expuso `GET /mi/configuracion`
  (mismo `ConfiguracionInstitucionalController::show()` que ya usaba el
  staff — el método no tenía nada específico de `staff`, solo hacía falta
  agregar la ruta bajo el guard `usuario`). El frontend reutiliza
  `utils/constancia.ts` (`generarConstanciaNoMulta`) sin cambios — el mismo
  generador de PDF sirve para los dos flujos, cambia solo de dónde vienen
  `usuario`/`configuracion` (`apiUsuario.ts` en vez de `api.ts`). Si
  agregás un endpoint nuevo de "mis X" en el portal, seguí este patrón
  (acotar a `$request->user()`, nunca aceptar un identificador de otro
  usuario por parámetro) en vez de reusar directamente un endpoint de
  staff que sí acepta RUT. Tests: `PortalMisMultasTest.php` (incluye un
  test explícito de que la deuda de otro usuario no se filtra).
- **Préstamo de equipos: de código de inventario en texto libre a código de
  barras real, más un tercer tipo "cargador"** (2026-08-16): un bibliotecario
  reportó que en la práctica el préstamo de audífonos/notebooks se hace
  escaneando un código de barras físico del equipo, igual que un libro —
  el sistema en cambio pedía tipear `codigo_inventario` (texto libre estilo
  "AUD-003") a mano, sin escaneo real. Se agregó `equipos.codigo_barras`
  (columna nueva, `unique`, obligatoria — migración
  `2026_08_16_000001_add_codigo_barras_to_equipos_table`, con backfill de
  placeholders largos para los equipos ya sembrados, mismo criterio que el
  resto de los códigos de barras inventados del proyecto) que pasó a ser el
  campo que efectivamente se escanea/tipea al prestar
  (`PrestamoController::store()` ahora busca `Equipo::where('codigo_barras',
  ...)`, igual que hace con `Ejemplar` para libros — `codigo_inventario` ya
  NO se acepta como identificador de préstamo). `codigo_inventario` se
  mantiene como el nombre legible que ve el staff en pantalla, y cambió de
  estilo: de códigos como "AUD-003" a nombres tipo "Notebook 01" (ver
  `SeedMockupData::seedEquipos()`) — es puramente para mostrar en UI/
  reportes, no se busca por él en ningún endpoint. Se agregó
  `EquipoController::buscarPorCodigo()` (`GET /equipos/{codigo}`, mismo
  patrón que `EjemplarController::buscarPorCodigo`) para lookups por
  código de barras. Se sumó **"cargador"** como tercer tipo de equipo
  prestable (junto a audífonos/notebooks) — requirió actualizar el CHECK
  constraint tanto de `equipos.tipo` como de `prestamos.tipo_item`
  (Postgres no permite alterar un CHECK existente, hay que dropearlo y
  recrearlo — migración `2026_08_16_000002_add_cargador_to_tipo_check_
  constraints`). Frontend: `EquiposView.vue` (formulario con
  nombre+código de barras+tipo, tercer `<option>` "Cargador"),
  `PrestamoView.vue` (tercera card "Préstamo de Cargadores", los 3 inputs
  de equipo pasaron de datalist-sobre-codigo_inventario a datalist-sobre-
  codigo_barras — la opción del datalist sigue mostrando el nombre legible
  como texto, pero el valor que se envía y compara es el código de
  barras). No reintroduzcas un flujo de préstamo de equipo que identifique
  por `codigo_inventario` — ya no es lo que se escanea. Tests:
  `EquipoPrestamoTest.php` (ampliado con casos de cargador y
  `buscarPorCodigo`), `PrestamoConcurrenciaTest.php` ajustado al nuevo
  payload.
- **Límite de bloques de sala por participante: máximo 2, y solo si son
  adyacentes en la MISMA sala** (2026-08-16): antes solo existía
  `participanteConReservaSolapada()` (rechaza reservas que se **solapan en
  horario** para un mismo RUT, en cualquier sala) — pero no había nada que
  impidiera a una persona reservar dos salas **distintas** en bloques que
  no se solapan (ej. sala A 10-12 y sala B 14-16), acaparando varias logias
  el mismo día. Se agregó
  `ReservaSalaService::participanteExcedeLimiteDeBloques()`: cada
  participante puede tener como máximo 2 reservas `estado = 'activa'` ese
  día, y si ya tiene 1, la nueva solo se permite si es en la **misma
  sala** y en el bloque **inmediatamente anterior o siguiente** (extender
  la estadía) — no ambas direcciones a la vez (con 2 ya alcanzó el
  máximo), y nunca una sala distinta aunque el horario no choque. Las
  reservas `'finalizada'` (llave ya devuelta) o `'no_show'` no cuentan —
  una vez que alguien termina, queda libre para reservar de nuevo más
  tarde ese mismo día. "Adyacente" se calcula comparando límites de hora
  (`existente.hora_fin === nueva.hora_inicio` o viceversa), no contra una
  lista fija de bloques — funciona igual sin importar si el staff reserva
  con horarios no estándar. Wireado en `SalaController::storeReserva` y
  `PortalController::reservarSala` (mismo patrón que el chequeo de
  solapamiento, un `Service` compartido en vez de duplicar la regla — ver
  convención 4); el mensaje 409 usa
  `ReservaSalaService::mensajeLimiteBloques()` en 2ª persona ("Ya
  tienes...") si el RUT que falla es el del propio usuario autenticado del
  portal, o en 3ª persona ("El RUT X ya tiene...") en cualquier otro caso
  (staff reservando para un grupo) — mismo criterio que ya usaba el
  mensaje de solapamiento. Tests: `SalaReservaTest.php` (7 casos nuevos,
  incluye el caso borde de "ya usó su única extensión, no puede pedir la
  otra dirección") + 2 nuevos en `PortalReservaTest.php`.
- **Confirmación antes de generar la Constancia de No Multa** (2026-08-16):
  antes el botón generaba y descargaba el PDF al primer click, sin aviso.
  Se agregó un modal de confirmación ("Se descargará un PDF...") en ambos
  lados — `UsuariosView.vue` (staff, `usuarioParaConstancia` +
  `pedirConfirmacionConstancia()`/`confirmarConstancia()`) y
  `PortalHomeView.vue` (portal, `confirmandoConstancia` +
  `confirmarConstancia()`) — mismo patrón de modal que ya se usa en el
  resto del proyecto (`@click.self` para cerrar clickeando afuera, botón
  "Cancelar" + botón de confirmar). El chequeo de multas pendientes sigue
  ocurriendo recién AL CONFIRMAR (no antes), así que el modal se muestra
  siempre, incluso para un usuario que después resulte bloqueado por
  deuda — el bloqueo se ve como el mismo toast de error de siempre tras
  confirmar, no cambia esa lógica.
- **RUT del usuario logueado precargado como "persona 1" al reservar sala
  desde el portal** (2026-08-16): `PortalSalasView.vue::openReservaModal()`
  ahora llena `rutsReserva[0]` con `auth.usuario?.rut` en vez de dejarlo
  vacío — el campo sigue siendo editable, solo cambia el valor inicial.
  No afecta la propiedad de la reserva: `PortalController::reservarSala()`
  ya usaba `$request->user()->id` (no `ruts[0]`) para `usuario_id` desde
  antes — este cambio es puramente UX, ahorra que el estudiante se
  tipee su propio RUT cada vez.
- **`libros.tipo_material` de enum fijo a catálogo administrable**
  (2026-08-19): igual que pasó con `Ubicacion` el 2026-08-15, el tipo de
  material (antes `libro|revista|tesis|dvd|otro`, `in:` + CHECK constraint)
  se convirtió en la tabla `tipos_material` — mismo patrón "admin-only" de
  la convención 8 (solo se crea desde `AdministracionView.vue`, todo staff
  solo lee). Migración `2026_08_19_000001_create_tipos_material_table`
  crea la tabla, la siembra con los 5 valores originales, agrega
  `libros.tipo_material_id` (FK, `nullOnDelete`) con backfill desde el
  valor de texto viejo, y dropea la columna + su CHECK constraint —
  irreversible sin pérdida si se agregan tipos nuevos después (mismo
  criterio que el resto de estas conversiones texto→catálogo). `Libro`
  gana `tipoMaterial(): BelongsTo` (nuevo modelo `TipoMaterial`, análogo a
  `Ubicacion`). `LibroController::index()` ordena "por tipo de recurso"
  con un `leftJoin` a `tipos_material` + `orderBy('tipos_material.nombre')`
  en vez de ordenar por el id de la FK (que no sería alfabético). El
  filtro de query pasó de `?tipo_material=` (string) a
  `?tipo_material_id=` (id) en `LibroController`/`EjemplarController`
  (cambio masivo). Frontend: `CatalogacionLibrosView.vue`,
  `CambioMasivoEstadoView.vue` y `ListadoLibrosView.vue` pasaron sus 3
  `<select>` hardcodeados a `v-for` sobre `GET /tipos-material`. No
  reintroduzcas el `in:libro,revista,tesis,dvd,otro` fijo — un tipo de
  material nuevo (ej. "Mapa") ahora se crea desde Administración, no
  editando código. Tests: `TipoMaterialTest.php`.
- **Historial de entradas: modo búsqueda por rango de fechas + RUT/nombre**
  (2026-08-19) — cerraba dos de los tres gaps reales del checklist de
  Horizon (puntos 6 y 10, ver tabla más arriba). Antes
  `EntradaController::index()` solo aceptaba `?fecha=` (un día exacto) —
  no había forma de auditar "¿cuándo vino esta persona?" ni "¿quién entró
  entre estas dos fechas?" sin revisar día por día a mano. Ya se agregó un
  segundo modo, activado si viene `desde`, `hasta` y/o `q` (cualquiera de
  los tres, no hace falta mandarlos todos): rango de fechas abierto por
  cualquiera de los dos extremos + texto libre por RUT/nombre, buscando
  tanto en `usuarios` (`whereHas('usuario', ...)`) como en
  `rut_externo`/`nombre_externo` (visitas/externos/convenio). El JSON de
  respuesta trae `modo: 'dia' | 'busqueda'` — en `'busqueda'` no viene
  `personasEnSala` (esa métrica solo tiene sentido para "hoy", no para un
  rango de varios días) y hay un tope de 500 filas (defensivo, un rango
  abierto sin más filtro podría devolver años de historial de una sola
  vez). Sin `desde`/`hasta`/`q`, el comportamiento es exactamente el de
  siempre (día exacto, default hoy) — no rompe nada existente. Igual que
  la búsqueda de Usuarios, **no normaliza puntos/guión del RUT** — el
  staff busca con el mismo formato con el que se muestra en pantalla
  (`12.345.678-5`), mismo criterio que `UsuarioController::index()`. No
  reintroduzcas la comparación de un solo día si volvés a tocar este
  controller. Frontend: `EntradaView.vue` suma un toggle "Por día" /
  "Buscar por rango / RUT / nombre" arriba del historial — en modo
  búsqueda aparecen inputs Desde/Hasta + texto, con debounce de 300ms, y
  la tabla suma una columna "Fecha" (oculta en modo día, donde sería
  redundante repetir la misma fecha en cada fila). Tests: 4 casos nuevos
  en `EntradaTest.php`.
- **Notificaciones por correo: implementadas pero sin SMTP real todavía**
  (2026-08-19) — antes no existía ningún `Mail::`/`Notification::` en todo
  el proyecto (`MAIL_MAILER` nunca se seteaba, caía al default de Laravel).
  Se agregaron dos notificaciones reales, no un stub: `App\Notifications\
  ReservaListaParaRetirarNotification` (se dispara desde
  `ReservaLibroService::liberarLibro()` cuando promueve a alguien de la
  cola de espera a `'pendiente'` — mismo método que usan tanto
  `PrestamoController::devolver()` como `ReservaLibroService::cancelar()`,
  así que cubre ambos disparadores sin duplicar lógica) y
  `App\Notifications\MultaGeneradaNotification` (se dispara desde
  `PrestamoController::devolver()` cuando `multa_estado` queda
  `'pendiente'`). `Usuario` ganó el trait `Notifiable` — el modelo ya
  tenía `email` fillable, así que el ruteo de mail por defecto de Laravel
  (usa el atributo `email`) funciona sin código adicional; si el usuario
  no tiene email, el guard explícito antes de `->notify()` evita el envío
  (aunque `MailChannel` igual lo saltaría solo). **A propósito no llevan
  `ShouldQueue`** — no hay ningún proceso `queue:work` corriendo en
  `docker-compose.yml` (`QUEUE_CONNECTION=sync` desde siempre), así que un
  `ShouldQueue` las dejaría en la tabla de jobs sin enviar nunca; se
  mandan sincrónicas, en línea, dentro del mismo request. **Por qué no
  rompe nada sin configurar un servidor de correo real**: `config/mail.php`
  cae a `env('MAIL_MAILER', 'log')` por default, y `.env.example` ahora lo
  deja explícito (`MAIL_MAILER=log`) con un comentario — con ese driver
  Laravel escribe el correo completo (HTML armado) en
  `storage/logs/laravel.log` en vez de intentar salir a la red, así que
  nunca falla ni bloquea el flujo de devolver/liberar un libro. Verificado
  manualmente el 2026-08-19: se forzó un préstamo atrasado, se devolvió
  por la API real (no un test con mock), y el correo completo apareció en
  el log sin errores. Cuando haya un SMTP real, activar cambiando
  `MAIL_MAILER=smtp` + `MAIL_HOST`/`MAIL_PORT`/`MAIL_USERNAME`/
  `MAIL_PASSWORD`/`MAIL_FROM_ADDRESS` en `.env` — **sin tocar código**. Se
  agregó `->salutation('Saludos,<br>Biblioteca UMAG')` en ambas
  notificaciones porque el saludo default de Laravel ("Regards,") sale en
  inglés (`APP_LOCALE` es `en`, nunca se publicaron los lang files en
  español) — si agregás una notificación nueva, no te olvides de este
  override o el correo queda mezclando español e inglés. **No implementado
  a propósito**: el aviso "tu préstamo vence mañana" (el tercer caso que se
  había mencionado como útil) necesitaría un job programado
  (`Schedule::` diario) revisando `prestamos` con `fecha_devolucion`
  próxima — a diferencia de las dos notificaciones de arriba, que se
  disparan solas desde un evento que ya ocurre en el código, esta
  necesitaría infraestructura de cron que hoy no existe en
  `docker-compose.yml` (ni `supervisor`, ni un contenedor separado
  corriendo `schedule:work`) — quedó fuera de este cambio, no es un
  olvido. Tests: `NotificacionesTest.php` (usa `Notification::fake()`,
  no depende de mail real).
- **Mockup de libros con campos de catalogación a medio llenar** (2026-08-19):
  `SeedMockupData::seedLibros()` solo llenaba `titulo`/`isbn` (y el `isbn` solo
  para 10 de los 34 libros — el resto quedaba `null`) además de autor/
  categoría/carrera; `tipo_material_id`/`editorial`/`anio_publicacion`/
  `clasificacion`/`coleccion` quedaban vacíos, y ningún `Ejemplar` tenía
  `ubicacion_id` (todos "sin especificar"). Se agregó generación inventada
  para los 5 primeros (ISBN real vía `fake()->isbn13()` para los que no
  traían uno de ejemplo, `tipo_material_id` = "Libro" para todos, editorial
  al azar de una lista corta, año 2005–2023, clasificación Dewey-ish
  mapeada por categoría vía `$deweyPorCategoria`) y `ubicacion_id` =
  "Biblioteca Central" (vía `Ubicacion::firstOrCreate`, no falla si la
  migración de seed no corrió) para todos los ejemplares nuevos. No
  reintroduzcas libros de mockup con estos campos en `null` — si agregás un
  título nuevo a `$catalogoLibros`, va a heredar el mismo relleno
  automáticamente sin cambios adicionales.

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
