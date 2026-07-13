# Biblioteca UMAG — Vue 3 + Laravel (reestructuración)

Migración del sistema original (Next.js + React) a **Vue 3 + Tailwind** (frontend)
y **Laravel + PostgreSQL** (backend API), 100% dockerizado.

## Estado actual

Todos los módulos principales están implementados y conectados end-to-end
(no quedan placeholders "Próximamente" en el sidebar):

| Módulo | Ruta staff | Backend |
|---|---|---|
| Dashboard | `/dashboard` | `DashboardController` |
| Usuarios | `/usuarios` (dropdown "Gestiones Admin") | `UsuarioController` |
| Entrada | `/entrada` | `EntradaController` |
| Préstamos | `/prestamo`, `/prestamos/listado` (dropdown) | `PrestamoController` |
| Salas (logias) | `/salas` | `SalaController` |
| Reportes | `/reportes` | `ReporteController` |
| Código QR de acceso | `/codigo-qr` (dropdown) | `CodigoAccesoController` |
| Listado de libros | `/libros/listado` (dropdown) | `LibroController` |
| Catalogación de libros | `/libros/catalogacion` (dropdown, **solo admin**) | `LibroController::store/update` |
| Estado de libro | `/libros/estado` (dropdown) | `LibroController::cambiarEstado` |

En el TopBar, los módulos secundarios (Usuarios, Listado Préstamos, Listado
Libros, Código QR) están agrupados bajo el botón desplegable **"Gestiones
Admin"** en vez de ir todos como links planos.

Además existe un **portal de autoservicio para usuarios** (no staff), con su
propio login y guard de rutas (`/portal/*`): marcar entrada/salida (RUT o
escaneo de QR con la cámara), consultar el catálogo de libros y reservar
salas de estudio.

Y un catálogo de libros conectado a préstamos y reservas por **código de
barras** (el código de barras es, en la práctica, la llave con la que se
identifica un libro en todo el sistema — nunca se elige por nombre/id):
`LibroController` (listado + búsqueda por código de barras),
`ReservaLibroController` (reservar un libro del catálogo para retiro) y
`PrestamoController` (préstamo real, con fecha de préstamo y de devolución
acordadas). Ambos flujos comparten el campo `libros.disponible` como fuente
de verdad — no se puede reservar ni prestar un libro que ya está ocupado por
otra persona (409), y se libera automáticamente al cancelar la reserva o
devolver el préstamo.

La catalogación de libros usa un formato tipo MARC/Horizon simplificado
(`clasificacion` Dewey+Cutter+año, `coleccion`, `editorial`,
`anio_publicacion`, `ubicacion`, `tipo_material`, `volumen`, notas pública/
interna, `precio`) y está restringida a staff con `rol = 'admin'`
(`LibroController::store/update`, protegidos con el middleware `admin`). El
ciclo de vida físico de cada ejemplar se gestiona aparte, en
`libros.estado_proceso` (`inventario` → `procesos_tecnicos` → `por_colocar`
→ `en_estante` / `estanteria_auxiliar` → `de_baja`), editable por cualquier
staff desde "Estado de Libro" — **es un eje distinto de `disponible`**: un
libro solo es prestable/reservable si está `en_estante` **y** `disponible`.
El catálogo público del portal (`/mi/catalogo`) solo muestra libros
`en_estante`.

También convive con **Horizon** (sistema externo que ya usa códigos de
barra reales) para registrar préstamo/devolución de logias y asistencia de
puestos de trabajo — ver `config/horizon_barcodes.php` y el comando
`horizon:codigos-logia` para cargar los códigos reales cuando Horizon los
entregue.

Auth: dos guards de Sanctum independientes — `staff` (bibliotecarios, vía
`AuthController`) y `usuario` (portal, vía `UsuarioAuthController`) —
implementados como middlewares (`EnsureIsStaff`, `EnsureIsUsuario`). Dentro
del guard `staff` hay además un rol (`staff.rol`: `admin` | `staff`),
verificado por el middleware `EnsureIsAdmin` (alias `admin`) — es el primer
chequeo de rol real del sistema, usado hoy solo para restringir la
catalogación de libros. En el frontend, el router respeta
`meta.requiresAdmin` y `TopBar.vue` oculta los links marcados `adminOnly`
cuando el staff logueado no es admin.

## Estructura

```
biblioteca-vue-laravel/
├── backend/              # Laravel API (se auto-instala dentro del contenedor)
│   ├── Dockerfile
│   ├── docker-entrypoint.sh
│   └── app-overlay/      # Nuestro código (Models, Controllers, routes, migrations, seeders)
├── frontend/              # Vue 3 + Vite + Tailwind + Pinia
│   └── src/
└── docker-compose.yml
```

### ¿Por qué "app-overlay"?

Laravel no se puede "vendorizar" fácilmente dentro de una imagen Docker sin antes
correr `composer create-project`. Por eso el `docker-entrypoint.sh` instala Laravel
la primera vez dentro de un volumen (`laravel_app`), y luego copia encima nuestro
código (`app-overlay/`) — modelos, controladores, rutas, migraciones y seeders.
En arranques posteriores, si Laravel ya existe en el volumen, solo se reaplica el
overlay y se corren migraciones — no se reinstala Composer desde cero.

## Cómo levantar el proyecto

Requisitos: Docker y Docker Compose instalados.

```bash
cd biblioteca-vue-laravel
docker compose up --build
```

Esto levanta:

| Servicio  | URL                          | Descripción                          |
|-----------|-------------------------------|---------------------------------------|
| frontend  | http://localhost:5173         | Vue 3 (Vite dev server, hot reload)  |
| backend   | http://localhost:8000         | Laravel API                          |
| db        | localhost:5432                | PostgreSQL                           |

**Primer arranque:** el backend tarda 1–3 minutos en levantar porque instala Laravel
y Composer dentro del contenedor. Verás el log `>> Creando proyecto Laravel base...`.
Arranques siguientes son casi instantáneos.

> Este `docker-compose.yml` es un entorno de **desarrollo** (bind mount del
> frontend, credenciales de Postgres hardcodeadas en el propio archivo). No
> está pensado como configuración de despliegue en producción.

### Credenciales de prueba

```
Email:    admin@umag.cl
Password: admin123
```

Estas credenciales, junto con usuarios, salas y movimientos de ejemplo, se cargan
automáticamente **solo la primera vez** que levantas el proyecto (si la tabla `staff`
está vacía). En arranques posteriores tus datos persisten — `docker compose up` ya
no borra la base cada vez.

## Cargar / regenerar datos de prueba manualmente

```bash
docker compose exec backend php artisan mockup:datos            # solo si no hay datos aún
docker compose exec backend php artisan mockup:datos --fresh    # borra todo y regenera desde cero
```

Este comando (`app/Console/Commands/SeedMockupData.php`) genera:

- 1 usuario `staff` (admin)
- 30 `usuarios` con RUT válido (con dígito verificador calculado), carrera (de las
  8 carreras UMAG), año de ingreso y sexo
- Entradas y préstamos distribuidos en los últimos días, con sesgo horario
  (más tráfico 10–13h y 15–18h), incluyendo ejemplos de entrada externa y de
  convenio para hoy
- 25 salas/logias de estudio (1er y 2do piso, capacidades variables) — cada
  una con su propio `codigo_barras` inventado (Horizon aún no entrega los
  reales) — y reservas de los últimos días

Si necesitas empezar completamente de cero (esquema incluido):

```bash
docker compose down -v
docker compose up --build
```

## Desarrollo del frontend fuera de Docker (opcional)

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

## Tests y benchmark de rendimiento

Hay una suite de Feature tests (`backend/app-overlay/tests/Feature/`) que
cubre login de staff/usuario, registro de entradas, reservas de sala
(solapamiento y validación grupal), cancelación de reservas ajenas y la
separación de middlewares `staff`/`usuario`. Corre contra una base Postgres
de pruebas dedicada (`biblioteca_test`, separada de `biblioteca`), que
`docker-entrypoint.sh` crea automáticamente al levantar el backend.

```bash
docker compose exec backend php artisan test
```

También hay un comando (`php artisan benchmark:api`) que mide latencia real
de la API vía HTTP (promedio, mediana, p95, máximo). Ver
[`backend/README.md`](backend/README.md) para el detalle de ambos, incluidas
las opciones disponibles y por qué se usa Postgres en vez de SQLite para los
tests.

## Deuda técnica conocida

Las reservas de sala siguen guardando los RUT de los participantes como un array JSON en vez de
una tabla relacional (`Reserva.ruts`), así que `SalaController::index`
reconstruye el mapeo RUT → usuario a mano. Los préstamos de **equipos**
(audífonos, notebooks) siguen identificándose por código de inventario en
texto libre, ya que no son parte del catálogo de libros. Los préstamos de
**libros**, en cambio, ya están conectados al catálogo real (`libro_id` +
`codigo_barras`, con validación de disponibilidad y de `estado_proceso`) —
pero todavía no existe un modelo de "ejemplar" (una fila `Libro` = un solo
`disponible`/`estado_proceso`, no soporta múltiples copias físicas del mismo
título). Tampoco se separa registro bibliográfico de ejemplar físico (sin
"Bib No." / "Item No." tipo Horizon) ni se guardan contadores históricos de
préstamo (número de préstamos, fecha del último) — son derivables por query
sobre `prestamos` si algún día hacen falta, a propósito no se duplicaron
como columnas. Antes de asumir que algo "falta" o "está roto", revisa el
código real en `app-overlay/` — este README se puede desactualizar.
