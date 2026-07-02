# Biblioteca UMAG — Vue 3 + Laravel (reestructuración)

Migración del sistema original (Next.js + React) a **Vue 3 + Tailwind** (frontend)
y **Laravel + PostgreSQL** (backend API), 100% dockerizado.

Este primer entregable incluye el módulo **Login + Dashboard**, con datos mockup
generados por el comando `php artisan mockup:datos`. El resto de los módulos
(`entrada`, `prestamo`, `usuarios`, `salas`, `reportes`) están como rutas
placeholder ("Próximamente") listas para migrarse en los siguientes sprints.

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
  8 carreras UMAG), año de ingreso y sexo — igual que `lib/mock-reportes.ts` del
  proyecto original
- Entradas y préstamos distribuidos en los últimos días, con el mismo sesgo horario
  del mock original (más tráfico 10–13h y 15–18h)
- 25 salas de estudio (1er piso, capacidades variables) y reservas del día —
  adaptado de `app/staff/salas/page.tsx`

## Reiniciar la base de datos con datos mockup frescos

```bash
docker compose exec backend php artisan mockup:datos --fresh
```

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

## Salas (API lista, vista pendiente)

`GET /api/salas?fecha=YYYY-MM-DD` devuelve las 25 salas y las reservas de ese día.
La vista Vue de este módulo sigue pendiente (por ahora está como "Próximamente"
en el sidebar).

## Próximos módulos a migrar

- [ ] Entradas (registro manual + QR, vista `kiosko`)
- [ ] Préstamos (creación, devolución, atrasos)
- [ ] Usuarios (CRUD, validación de RUT chileno)
- [ ] Salas (reservas)
- [ ] Reportes (equivalente a `lib/mock-reportes.ts` del sistema original)

Cada uno seguirá el mismo patrón: `app-overlay/` (Model + Controller + rutas) en
Laravel, y una vista Vue con su propio store en Pinia, consumiendo la API real con
fallback a mockup si el backend no responde (igual que el Dashboard).
