<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Este seeder ya no se ejecuta automáticamente al levantar el contenedor.
     *
     * Para cargar datos de prueba (staff, usuarios con carrera, salas,
     * entradas, préstamos y reservas), usa el comando dedicado:
     *
     *   docker compose exec backend php artisan mockup:datos
     *   docker compose exec backend php artisan mockup:datos --fresh   (para regenerar todo)
     *
     * Ver: app/Console/Commands/SeedMockupData.php
     */
    public function run(): void
    {
        $this->command?->comment('Este seeder está deshabilitado. Usa: php artisan mockup:datos');
    }
}
