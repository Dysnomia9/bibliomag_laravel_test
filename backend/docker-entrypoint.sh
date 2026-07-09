#!/usr/bin/env bash
set -e

cd /var/www

# 1) Si el proyecto Laravel aún no existe en el volumen, crearlo.
if [ ! -f "artisan" ]; then
  echo ">> Creando proyecto Laravel base..."
  # Se instala en un directorio temporal y luego se copia a /var/www.
  # Motivo: los volúmenes nuevos de Docker sobre ext4 traen una carpeta
  # "lost+found" por defecto, lo que hace que Composer crea que el
  # directorio destino no está vacío y aborte create-project.
  rm -rf /tmp/laravel-fresh
  # Laravel 11 queda bloqueado por advisories de Composer al resolver
  # laravel/framework; usar Laravel 12 evita bootstrappear una rama insegura.
  composer create-project laravel/laravel:^12.0 /tmp/laravel-fresh --no-interaction --prefer-dist --no-audit

  echo ">> Instalando Laravel Sanctum (auth API)..."
  cd /tmp/laravel-fresh
  composer require laravel/sanctum --no-interaction --no-audit
  php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --no-interaction

  echo ">> Copiando proyecto al volumen persistente..."
  cp -a /tmp/laravel-fresh/. /var/www/
  rm -rf /tmp/laravel-fresh
  cd /var/www

  # IMPORTANTE: composer create-project ya generó un .env por defecto
  # (con DB_CONNECTION=sqlite). Lo sobreescribimos con nuestra plantilla
  # de Postgres/Docker ANTES de seguir, o Laravel seguiría usando SQLite.
  echo ">> Configurando .env para PostgreSQL (Docker)..."
  cp -f /app-overlay/.env.example /var/www/.env
  php artisan key:generate --no-interaction
fi

# 2) Aplicar/actualizar el overlay de nuestra aplicación (Models, Controllers, routes, migrations, seeders)
echo ">> Aplicando overlay de la app biblioteca..."
cp -r /app-overlay/app/. /var/www/app/
cp -r /app-overlay/routes/. /var/www/routes/
cp -r /app-overlay/database/. /var/www/database/
cp -r /app-overlay/config/. /var/www/config/
cp -r /app-overlay/tests/. /var/www/tests/
cp /app-overlay/phpunit.xml /var/www/phpunit.xml
cp /app-overlay/bootstrap/app.php /var/www/bootstrap/app.php
cp /app-overlay/.env.example /var/www/.env.example

# 3) Configurar .env si no existe
if [ ! -f ".env" ]; then
  cp .env.example .env
  php artisan key:generate --no-interaction
fi

# Forzar variables de entorno de Docker en .env (DB, CORS, etc.)
php artisan config:clear

echo ">> Esperando a la base de datos..."
until php artisan db:show > /dev/null 2>&1; do
  sleep 1
done

# La suite de tests (tests/Feature) corre contra una base Postgres de pruebas
# separada ('biblioteca_test'), no contra 'biblioteca' (desarrollo/demo) — ver
# phpunit.xml y backend/README.md. Se crea aquí de forma idempotente para que
# "docker compose up" deje todo listo para `php artisan test` sin pasos manuales.
echo ">> Verificando base de datos de pruebas (biblioteca_test)..."
php -r '
$pdo = new PDO(
    "pgsql:host=" . getenv("DB_HOST") . ";port=" . (getenv("DB_PORT") ?: 5432) . ";dbname=" . getenv("DB_DATABASE"),
    getenv("DB_USERNAME"),
    getenv("DB_PASSWORD")
);
$exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '\''biblioteca_test'\''")->fetchColumn();
if (! $exists) {
    $pdo->exec("CREATE DATABASE biblioteca_test");
    echo "   Creada.\n";
} else {
    echo "   Ya existe.\n";
}
'

echo ">> Corriendo migraciones..."
php artisan migrate --force

echo ">> Verificando datos de prueba..."
STAFF_COUNT=$(php artisan tinker --execute="echo \App\Models\Staff::count();" 2>/dev/null | tr -d '[:space:]')
if [ "$STAFF_COUNT" = "0" ] || [ -z "$STAFF_COUNT" ]; then
  echo ">> No hay datos de prueba aún, cargando mockup inicial (staff, usuarios, salas, entradas, préstamos)..."
  php artisan mockup:datos
else
  echo ">> Ya existen datos de prueba ($STAFF_COUNT staff). Usa 'php artisan mockup:datos --fresh' para regenerarlos."
fi

echo ">> Levantando servidor Laravel en 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
