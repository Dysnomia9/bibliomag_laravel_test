# Biblioteca UMAG en Vue 3 + Laravel

Sistema de biblioteca universitaria que reemplaza a **Horizon** (SirsiDynix)
por una plataforma propia: **Vue 3 + Tailwind** (frontend) y **Laravel +
PostgreSQL** (backend API), 100% dockerizada.

## Qué se puede hacer

**Personal de biblioteca (staff)**

- Dashboard con indicadores en tiempo real: usuarios activos, entradas de
  hoy, personas en sala, préstamos activos y atrasados.
- Registro de entradas por RUT, QR o manual — incluye visitantes externos y
  de convenio.
- Préstamos y devoluciones de libros (por código de barras, con fecha de
  préstamo/devolución acordada) y de equipos — audífonos y notebooks —
  desde un inventario real con control de disponibilidad.
- Multas automáticas por atraso al devolver un libro: tarifa configurable
  en `config/multas.php`, aviso al staff si el usuario ya tiene deuda
  pendiente al crear un préstamo nuevo, y una vista "Multas Pendientes" con
  el total adeudado por usuario.
- Reservas de salas de estudio (logias, por bloques de 2 horas) y de libros
  del catálogo para retiro — ambas comparten `libros.disponible` como
  fuente de verdad: no se puede reservar ni prestar algo ya ocupado.
- Catalogación de libros (formato MARC/Dewey simplificado — solo admin) y
  gestión del estado físico de cada ejemplar (inventario → en estante → de
  baja, etc.), como eje independiente de su disponibilidad.
- Gestión de usuarios, equipos (agregar/dar de baja), listado de préstamos
  y listado de libros — agrupados en el menú "Gestiones Admin".
- Reportes con gráficos (préstamos, ingresos, uso de logias) filtrables por
  período, carrera, sexo y tipo de usuario.
- Código QR de acceso compartido y regenerable, para que los usuarios
  marquen su entrada por su cuenta.

**Portal de autoservicio (usuarios finales, sin ser staff)**

- Login propio, independiente del panel de personal.
- Marcar entrada/salida por RUT o escaneando el QR con la cámara.
- Consultar el catálogo de libros disponibles.
- Reservar salas de estudio.

**Backend / integraciones**

- Dos guards de autenticación independientes vía Sanctum (`staff` y
  `usuario`), más un rol `admin` dentro de `staff` que restringe acciones
  como la catalogación de libros.
- Compatibilidad con los lectores de código de barras de Horizon para
  logias y puestos de trabajo (sin sincronizar datos con Horizon — ver
  Deuda técnica).

## Estructura

```
biblioteca-vue-laravel/
├── backend/              # Proyecto Laravel completo y normal (API-only)
│   ├── Dockerfile
│   ├── docker-entrypoint.sh
│   ├── app/               # Models, Controllers, Middleware, Services, Console/Commands
│   ├── config/ database/ routes/ tests/ bootstrap/  # lo que editamos habitualmente
│   └── public/ resources/ storage/ artisan composer.json  # esqueleto estándar de Laravel
├── frontend/              # Vue 3 + Vite + Tailwind + Pinia
│   └── src/
└── docker-compose.yml
```

| Módulo | Ruta staff | Backend |
|---|---|---|
| Dashboard | `/dashboard` | `DashboardController` |
| Usuarios | `/usuarios` (dropdown "Gestiones Admin") | `UsuarioController` |
| Entrada | `/entrada` | `EntradaController` |
| Préstamos | `/prestamo`, `/prestamos/listado` (dropdown) | `PrestamoController` |
| Equipos | `/equipos` (dropdown, **solo admin**) | `EquipoController` |
| Salas (logias) | `/salas` | `SalaController` |
| Multas Pendientes | `/reportes/multas-pendientes` (dropdown) | `ReporteController::multasPendientes` |
| Reportes | `/reportes` | `ReporteController` |
| Código QR de acceso | `/codigo-qr` (dropdown) | `CodigoAccesoController` |
| Listado de libros | `/libros/listado` (dropdown) | `LibroController` |
| Catalogación de libros | `/libros/catalogacion` (dropdown, **solo admin**) | `LibroController::store/update` |
| Estado de libro | `/libros/estado` (dropdown) | `LibroController::cambiarEstado` |

### Cómo se arma la imagen del backend

`backend/Dockerfile` es un Dockerfile Laravel estándar: `composer install`
corre en tiempo de **build** (capa cacheada por Docker — solo se reinstala
si `composer.json`/`composer.lock` cambian) y `COPY . .` copia el proyecto
completo, ya armado, a la imagen. La imagen queda autocontenida: no depende
de ningún volumen para tener el código, y el primer arranque no necesita
red para instalar nada.

`docker-entrypoint.sh` solo hace trabajo de **runtime**: esperar a que la
base de datos esté lista, correr migraciones, cargar datos de prueba si
hace falta, y levantar el servidor.

(Hasta el 2026-07-17 esto se resolvía distinto — un "overlay" que se
aplicaba en el primer arranque del contenedor sobre un Laravel instalado en
un volumen. Se simplificó a un Dockerfile normal porque no aportaba
ninguna ventaja real sobre el patrón estándar y hacía la imagen depender de
red/volumen en el primer arranque — mala base para un despliegue real.)

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

**Primer arranque:** la imagen del backend puede tardar un par de minutos en
construirse la primera vez (`composer install` corre en el build). Una vez
construida, arrancar y rearmar contenedores es rápido — el código ya está
horneado en la imagen, no se reinstala nada al levantar.

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
- 7 equipos (audífonos y notebooks) y sus préstamos asociados
- 25 salas/logias de estudio (1er y 2do piso, capacidades variables) — cada
  una con su propio `codigo_barras` inventado (Horizon aún no entrega los
  reales) — y reservas de los últimos días, con sus participantes reales

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

Hay una suite de Feature tests (`backend/tests/Feature/`) que
cubre login de staff/usuario, registro de entradas, reservas de sala
(solapamiento y validación grupal, incluido el cruce entre salas distintas),
préstamos de equipos, cálculo/cobro de multas por atraso y su reporte
consolidado, y la separación de middlewares `staff`/`usuario`. Corre contra
una base Postgres de pruebas dedicada (`biblioteca_test`, separada de
`biblioteca`), que `docker-entrypoint.sh` crea automáticamente al levantar
el backend.

```bash
docker compose exec backend php artisan test
```

También hay un comando (`php artisan benchmark:api`) que mide latencia real
de la API vía HTTP (promedio, mediana, p95, máximo). Ver
[`backend/README.md`](backend/README.md) para el detalle de ambos, incluidas
las opciones disponibles y por qué se usa Postgres en vez de SQLite para los
tests.

## Deuda técnica conocida

- **Sin múltiples copias de un mismo libro**: una fila `Libro` sigue siendo
  una sola copia física (un solo `disponible`/`estado_proceso`); no hay
  modelo de "ejemplar", ni separación entre registro bibliográfico e ítem
  físico (sin "Bib No." / "Item No." estilo Horizon). Fuera del alcance
  actual — ver `CLAUDE.md` si se retoma.
- **Multas sin bloqueo duro**: se avisa al staff al crear un préstamo si el
  usuario tiene deuda pendiente, y existe una vista consolidada por usuario,
  pero ningún préstamo se rechaza por eso — es una decisión deliberada, no
  un bug.
- **Contadores históricos de préstamo** (cantidad, última fecha) no se
  guardan como columnas — son derivables por query sobre `prestamos` si
  algún día hacen falta.
- **Credenciales de Postgres hardcodeadas** en `docker-compose.yml` —
  aceptable en desarrollo local, no usar tal cual en un despliegue.
- **`PortalController` concentra varias responsabilidades** (estado/aforo,
  entrada/salida, catálogo, salas y reservas del usuario). Si crece más,
  conviene separar por dominio en vez de agregar más métodos ahí.

Antes de asumir que algo "falta" o "está roto", revisa el código real en
`backend/` — este README se puede desactualizar.
