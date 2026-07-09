# Biblioteca UMAG — Vue 3 + Laravel (reestructuración)

Migración del sistema original (Next.js + React) a **Vue 3 + Tailwind** (frontend)
y **Laravel + PostgreSQL** (backend API), 100% dockerizado.

## Estado actual

Todos los módulos principales están implementados y conectados end-to-end
(no quedan placeholders "Próximamente" en el sidebar):

| Módulo | Ruta staff | Backend |
|---|---|---|
| Dashboard | `/dashboard` | `DashboardController` |
| Usuarios | `/usuarios` | `UsuarioController` |
| Entrada | `/entrada` | `EntradaController` |
| Préstamos | `/prestamo`, `/prestamos/listado` | `PrestamoController` |
| Salas | `/salas` | `SalaController` |
| Reportes | `/reportes` | `ReporteController` |
| Código QR de acceso | `/codigo-qr` | `CodigoAccesoController` |

Además existe un **portal de autoservicio para usuarios** (no staff), con su
propio login y guard de rutas (`/portal/*`): marcar entrada/salida (RUT o
escaneo de QR con la cámara), consultar el catálogo de libros y reservar
salas de estudio.

Y un catálogo de libros incipiente, separado de los préstamos "texto libre":
`LibroController` (búsqueda por código de barras) y `ReservaLibroController`
(reservar un libro del catálogo para retiro).

Auth: dos guards de Sanctum independientes — `staff` (bibliotecarios, vía
`AuthController`) y `usuario` (portal, vía `UsuarioAuthController`) —
implementados como middlewares (`EnsureIsStaff`, `EnsureIsUsuario`).

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
  (más tráfico 10–13h y 15–18h)
- 25 salas de estudio (1er piso, capacidades variables) y reservas del día

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

## Deuda técnica conocida

No hay una suite de tests automatizados en el repo. Algunos puntos del modelo
de datos y de la capa de autorización quedan pendientes de endurecer (por
ejemplo: los préstamos siguen guardando `libro_titulo` como texto libre en
vez de referenciar el catálogo de libros/ejemplares, y las reservas de sala
guardan los RUT de los participantes como un array JSON en vez de una tabla
relacional). Antes de asumir que algo "falta" o "está roto", revisa el
código real en `app-overlay/` — este README se puede desactualizar.
