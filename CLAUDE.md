# Biblioteca UMAG — Contexto del proyecto para Claude Code

## Qué es esto

Reestructuración de un sistema de biblioteca universitaria (UMAG, Punta Arenas)
desde **Next.js + React + Tailwind** hacia **Vue 3 + Tailwind (frontend)** y
**Laravel + PostgreSQL (backend API)**, 100% dockerizado.

- **Repo actual (este):** el que estás por editar.
- **Repo original de referencia (solo lectura):**
  `https://github.com/Dysnomia9/biblioteca_sistema_docker_sumag`
  Úsalo para extraer **estructura de datos, textos, reglas de negocio y UX de
  referencia** de los módulos que aún faltan — NO para copiar código React
  literal (el stack cambió a Vue).

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
- Login: `admin@umag.cl` / `admin123`

## Estructura relevante

```
backend/
  docker-entrypoint.sh        # instala Laravel en frío la 1ra vez, aplica overlay, migra, seedea
  app-overlay/                # ÚNICO lugar del backend que se edita — se "hornea" en la imagen
    app/Models/                Staff, Usuario, Entrada, Prestamo, Sala, Reserva
    app/Http/Controllers/Api/  AuthController, DashboardController, SalaController
    app/Console/Commands/      SeedMockupData.php (comando `mockup:datos`)
    database/migrations/
    routes/api.php
    bootstrap/app.php          # NO tiene statefulApi() — auth es Bearer token puro, sin CSRF

frontend/
  src/
    views/          LoginView, DashboardView, ProximamenteView (placeholder)
    components/layout/  StaffLayout, SidebarNav, TopBar
    stores/auth.ts  Pinia store con login/logout, token en localStorage
    services/api.ts Cliente axios (interceptor agrega Bearer token)
    router/index.ts Rutas + guard de auth
    types/index.ts  Tipos TS que reflejan los modelos de Laravel
    data/mock.ts    Fallback local si la API no responde
```

## Convenciones a seguir en los módulos nuevos

1. **Capa Vue:** cada módulo es una vista en `src/views/`, envuelta en
   `<StaffLayout>`, con su propio store en Pinia si maneja estado propio de CRUD.
   Seguir el patrón de `DashboardView.vue`: `onMounted` llama a la API real vía
   `api.ts`, y si falla cae a datos mock locales con un badge visible
   ("Mostrando datos de ejemplo") — nunca dejar la pantalla en blanco si el
   backend no responde.
2. **Responsive:** mobile-first con Tailwind (`grid-cols-2 sm:grid-cols-3
   lg:grid-cols-5`, etc.), igual que el Dashboard. Nada de tablas que rompan
   el layout en mobile — usar scroll horizontal contenido o cards apiladas.
3. **Paleta:** usar los colores `biblioteca-*` y `acento-*` definidos en
   `tailwind.config.js` 
   gradientes morados/índigo tipo SaaS del proyecto original.
4. **Backend:** un Controller + rutas protegidas por `auth:sanctum` por
   módulo, siguiendo el patrón de `DashboardController`/`SalaController`.
   Cualquier migración nueva va con timestamp correlativo en
   `database/migrations/` (nunca editar una migración ya aplicada — crear una
   nueva `alter table` si hace falta cambiar un esquema existente).
5. Reemplazar la ruta placeholder correspondiente en `router/index.ts` y el
   ícono/link ya existente en `SidebarNav.vue` (no hay que tocar el sidebar,
   los links ya apuntan a los `name` correctos: `entrada`, `prestamo`,
   `usuarios`, `salas`, `reportes`).

## Módulos pendientes (orden sugerido)

### 1. Usuarios (`/usuarios`) — recomendado empezar por acá, es la base de todo lo demás

- CRUD completo: listar (con búsqueda por nombre/RUT/carrera), crear, editar,
  activar/desactivar (soft — no hay `deleted_at`, usar el campo `activo`).
- El modelo `Usuario` ya tiene `rut`, `nombre`, `apellido`, `email`, `tipo`,
  `carrera`, `anio_ingreso`, `sexo`, `activo`, `qr_code`.
- **Validación de RUT chileno**: el repo original tiene la lógica de
  formateo/validación en `lib/rut.ts` — pórtala a un composable Vue
  (`src/composables/useRut.ts` o similar) y a un `FormRequest` de Laravel
  para el backend. Revisa ese archivo en el repo original antes de reinventar
  el algoritmo del dígito verificador (el comando `mockup:datos` ya tiene una
  implementación en PHP en `SeedMockupData::digitoVerificador()` que puedes
  reutilizar/extraer a un helper compartido).
- Backend: falta `UsuarioController` (index con filtros, store, update) y
  las rutas correspondientes.

### 2. Entradas (`/entrada`)

- En el original hay una vista `app/kiosko/` (pantalla pública tipo totem
  para registrar entrada, probablemente por RUT o QR) y `app/staff/entrada/`
  (vista de staff). Revisa ambas antes de diseñar el flujo — puede que
  convenga separar "Kiosko" (pantalla pública, sin auth) de "Entrada" (panel
  de staff con historial y registro manual).
- El modelo `Entrada` ya existe (`usuario_id`, `fecha_hora_entrada`,
  `fecha_hora_salida`, `via: manual|qr`).
- Falta: `EntradaController` (listar con filtros de fecha, registrar entrada,
  marcar salida) y la vista Vue con formulario de búsqueda de usuario por RUT
  + botón de registrar entrada/salida.

### 3. Préstamos (`/prestamo`)

- Modelo `Prestamo` ya existe (`usuario_id`, `libro_titulo`,
  `fecha_prestamo`, `fecha_devolucion`, `fecha_devolucion_real`,
  `estado: activo|atrasado|devuelto`).
- Falta: `PrestamoController` (crear préstamo, marcar devuelto, listar con
  filtro por estado) y vista con tabla de préstamos activos/atrasados +
  modal para crear uno nuevo.
- Nota: el campo es `libro_titulo` (texto libre), no hay catálogo de libros
  todavía — revisa si el repo original tiene un catálogo real antes de
  decidir si conviene normalizar esto a una tabla `libros`.

### 4. Salas (`/salas`) — backend ya adelantado

- Modelos `Sala` y `Reserva` ya existen, con 25 salas (1er piso, capacidad
  variable) generadas por `mockup:datos`.
- **Ya existe** `GET /api/salas?fecha=YYYY-MM-DD` (devuelve salas + reservas
  del día). Falta: `POST /api/reservas` (crear) y
  `DELETE /api/reservas/{id}` (cancelar).
- Referencia de UX: `app/staff/salas/page.tsx` del repo original — es una
  grilla de 25 salas × 6 bloques horarios de 2h (08-20h), click en un bloque
  libre abre modal para reservar con RUT + nombre, bloques ocupados muestran
  quién reservó y permiten cancelar. Adapta ese mismo patrón a Vue (grid con
  scroll horizontal en mobile).

### 5. Reportes (`/reportes`)

- El repo original tiene toda la lógica de agregación en
  `lib/mock-reportes.ts`: agrupa préstamos/ingresos por período (día, semana,
  mes, semestre, año) y por carrera/sexo/tipo de usuario, con funciones
  `bucketKey`/`bucketLabel` para las etiquetas de cada agrupación.
- Esto conviene resolverlo del lado del backend (Laravel) con queries
  agregadas (`GROUP BY`) sobre `entradas` y `prestamos` — ya tenemos
  `carrera`/`sexo`/`anio_ingreso` en `usuarios`, así que se puede hacer un
  `ReporteController` con endpoints tipo
  `GET /api/reportes/prestamos?periodo=mes&desde=...&hasta=...`.
- Vista Vue: gráficos con alguna librería ligera (Chart.js ya está permitida
  en el entorno de artifacts, pero para este frontend real evalúa agregarla
  a `package.json` si hace falta) + selector de período.

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
- **Sin rate limiting en login**: ya se agregó `throttle:6,1` en la ruta
  `POST /auth/login` (`routes/api.php`) — no lo quites, es la única
  protección contra fuerza bruta que tiene el sistema ahora mismo.
- **Seed no destructivo**: el entrypoint corre `migrate --force` (no
  `migrate:fresh`) y solo ejecuta `mockup:datos` automáticamente si la tabla
  `staff` está vacía. Los datos de prueba ya NO se borran en cada
  `docker compose up`.

## Checklist antes de dar un módulo por terminado

- [ ] Responsive real probado en mobile (no solo con dev tools, si es posible)
- [ ] La vista tiene fallback a mockup si la API falla (patrón del Dashboard)
- [ ] Las rutas nuevas del backend están protegidas con `auth:sanctum`
- [ ] Si hay migración nueva, es un archivo nuevo (no editaste una existente)
- [ ] El link del sidebar y la ruta en `router/index.ts` quedaron conectados
      al componente real (ya no al `ProximamenteView`)
- [ ] `docker compose exec backend php artisan mockup:datos --fresh` sigue
      corriendo sin errores después del cambio
