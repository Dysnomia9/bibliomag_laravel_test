<?php

namespace App\Console\Commands;

use App\Models\Entrada;
use App\Models\Prestamo;
use App\Models\Reserva;
use App\Models\Sala;
use App\Models\Staff;
use App\Models\Usuario;
use App\Support\Rut;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SeedMockupData extends Command
{
    protected $signature = 'mockup:datos {--fresh : Borra los datos de prueba existentes antes de crear los nuevos}';

    protected $description = 'Carga datos de prueba (mockup) para desarrollo: staff, usuarios con carrera, salas, entradas, préstamos y reservas.';

    /** Carreras UMAG — adaptado de lib/mock-reportes.ts (proyecto Next.js original) */
    private array $carreras = [
        'Ingeniería Civil Informática',
        'Ingeniería Comercial',
        'Derecho',
        'Enfermería',
        'Trabajo Social',
        'Pedagogía en Educación Básica',
        'Medicina Veterinaria',
        'Construcción Civil',
    ];

    private array $aniosIngreso = [2021, 2022, 2023, 2024, 2025, 2026];

    private array $sexos = ['Femenino', 'Masculino'];

    private array $tiposUsuario = ['estudiante', 'estudiante', 'estudiante', 'docente', 'funcionario'];

    private array $nombres = [
        'Camila', 'Matías', 'Francisca', 'Benjamín', 'Valentina', 'Diego', 'Javiera', 'Andrés',
        'Sofía', 'Felipe', 'Antonia', 'Carlos', 'Fernanda', 'Ignacio', 'Catalina', 'Pedro',
        'Josefa', 'Rodrigo', 'Isidora', 'Tomás', 'Constanza', 'Sebastián', 'Trinidad', 'Nicolás',
        'Florencia', 'Vicente', 'Amanda', 'Gabriel', 'Emilia', 'Joaquín',
    ];

    private array $apellidos = [
        'Soto', 'Vera', 'Muñoz', 'Alvarado', 'Ríos', 'Morales', 'Espinoza', 'Vargas',
        'Contreras', 'Rojas', 'Bravo', 'Díaz', 'Torres', 'Herrera', 'Fernández', 'Pérez',
        'Gómez', 'Sánchez', 'Reyes', 'Castro',
    ];

    private array $librosCatalogo = [
        'Estructuras de Datos y Algoritmos',
        'Redes de Computadores',
        'Cálculo I',
        'Ingeniería de Software',
        'Bases de Datos',
        'Introducción al Derecho Civil',
        'Anatomía y Fisiología',
        'Fundamentos de Trabajo Social',
        'Didáctica General',
        'Producción Animal',
    ];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->info('Eliminando datos de prueba existentes...');
            DB::table('reservas')->delete();
            DB::table('entradas')->delete();
            DB::table('prestamos')->delete();
            DB::table('usuarios')->delete();
            DB::table('salas')->delete();
            DB::table('staff')->delete();
        } elseif (Staff::count() > 0) {
            $this->warn('Ya existen datos de prueba. Usa --fresh para regenerarlos desde cero.');

            return self::SUCCESS;
        }

        $this->seedStaff();
        $usuarios = $this->seedUsuarios(30);
        $this->seedEntradas($usuarios);
        $this->seedPrestamos($usuarios);
        $salas = $this->seedSalas();
        $this->seedReservas($salas, $usuarios);

        $this->info('Datos de prueba cargados correctamente.');

        return self::SUCCESS;
    }

    private function seedStaff(): void
    {
        Staff::create([
            'email' => 'admin@umag.cl',
            'password' => Hash::make('admin123'),
            'nombre' => 'Ignacio Contreras',
            'rol' => 'admin',
        ]);

        $this->line('  · Staff creado (admin@umag.cl / admin123)');
    }

    /** @return \Illuminate\Support\Collection<int, Usuario> */
    private function seedUsuarios(int $cantidad)
    {
        $usuarios = collect();
        $rutBase = 15000000;

        for ($i = 0; $i < $cantidad; $i++) {
            $nombre = $this->nombres[array_rand($this->nombres)];
            $apellido = $this->apellidos[array_rand($this->apellidos)];
            $rutNum = $rutBase + ($i * 137);

            $usuarios->push(Usuario::create([
                'rut' => $this->formatearRut($rutNum),
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => strtolower($nombre.'.'.$apellido.$i).'@umag.cl',
                'tipo' => $this->tiposUsuario[array_rand($this->tiposUsuario)],
                'carrera' => $this->carreras[array_rand($this->carreras)],
                'anio_ingreso' => $this->aniosIngreso[array_rand($this->aniosIngreso)],
                'sexo' => $this->sexos[array_rand($this->sexos)],
                'activo' => true,
                'qr_code' => strtoupper(uniqid('QR')),
            ]));
        }

        $this->line("  · {$cantidad} usuarios creados (con carrera, año de ingreso y sexo)");

        return $usuarios;
    }

    private function seedEntradas($usuarios): void
    {
        $total = 0;

        foreach ($usuarios as $i => $usuario) {
            // Cada usuario tiene entre 1 y 4 entradas distribuidas en los últimos 14 días
            $numEntradas = random_int(1, 4);

            for ($j = 0; $j < $numEntradas; $j++) {
                $diasAtras = random_int(0, 13);
                $hora = $this->horaConSesgo();
                $entrada = now()->subDays($diasAtras)->setTime($hora, random_int(0, 59));
                $conSalida = random_int(0, 100) < 70;

                Entrada::create([
                    'usuario_id' => $usuario->id,
                    'fecha_hora_entrada' => $entrada,
                    'fecha_hora_salida' => $conSalida ? $entrada->copy()->addMinutes(random_int(30, 180)) : null,
                    'via' => random_int(0, 100) < 35 ? 'qr' : 'manual',
                ]);
                $total++;
            }
        }

        $this->line("  · {$total} entradas creadas (últimos 14 días)");
    }

    private function seedPrestamos($usuarios): void
    {
        $total = 0;

        foreach ($usuarios as $i => $usuario) {
            if (random_int(0, 100) > 60) {
                continue; // no todos los usuarios tienen préstamos
            }

            $numPrestamos = random_int(1, 2);

            for ($j = 0; $j < $numPrestamos; $j++) {
                $diasAtras = random_int(0, 20);
                $fechaPrestamo = now()->subDays($diasAtras);
                $fechaDevolucion = $fechaPrestamo->copy()->addDays(7);

                $devuelto = random_int(0, 100) < 40;
                $atrasado = ! $devuelto && $fechaDevolucion->isPast();

                Prestamo::create([
                    'usuario_id' => $usuario->id,
                    'libro_titulo' => $this->librosCatalogo[array_rand($this->librosCatalogo)],
                    'fecha_prestamo' => $fechaPrestamo,
                    'fecha_devolucion' => $fechaDevolucion,
                    'fecha_devolucion_real' => $devuelto ? $fechaDevolucion->copy()->subDays(random_int(0, 3)) : null,
                    'estado' => $devuelto ? 'devuelto' : ($atrasado ? 'atrasado' : 'activo'),
                ]);
                $total++;
            }
        }

        $this->line("  · {$total} préstamos creados");
    }

    /** @return \Illuminate\Support\Collection<int, Sala> */
    private function seedSalas()
    {
        // 25 salas de estudio, 1er piso — adaptado de app/staff/salas/page.tsx
        $capacidades = [2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3, 4, 2];
        $salas = collect();

        for ($i = 0; $i < 25; $i++) {
            $salas->push(Sala::create([
                'nombre' => 'Sala '.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'capacidad' => $capacidades[$i],
                'piso' => '1er Piso',
            ]));
        }

        $this->line('  · 25 salas de estudio creadas');

        return $salas;
    }

    private function seedReservas($salas, $usuarios): void
    {
        $bloques = [
            [8, 10], [10, 12], [12, 14], [14, 16], [16, 18], [18, 20],
        ];

        $hoy = now()->toDateString();
        $total = 0;

        // Reservas de hoy: ~40% de los bloques ocupados, distribuidos en salas al azar
        foreach ($salas as $sala) {
            foreach ($bloques as [$inicio, $fin]) {
                if (random_int(0, 100) > 40) {
                    continue;
                }

                $usuario = $usuarios->random();

                Reserva::create([
                    'sala_id' => $sala->id,
                    'usuario_id' => $usuario->id,
                    'nombre_usuario' => "{$usuario->nombre} {$usuario->apellido}",
                    'rut_usuario' => $usuario->rut,
                    'fecha' => $hoy,
                    'hora_inicio' => $inicio,
                    'hora_fin' => $fin,
                    'estado' => 'activa',
                ]);
                $total++;
            }
        }

        $this->line("  · {$total} reservas de sala creadas (hoy)");
    }

    /** Genera un RUT chileno válido con dígito verificador a partir de un número base */
    private function formatearRut(int $numero): string
    {
        return Rut::formatear($numero);
    }

    /** Horario biblioteca 8-20 hrs, más concurrido 10-13 y 15-18 (mismo sesgo que el mock original) */
    private function horaConSesgo(): int
    {
        $r = random_int(0, 100) / 100;

        if ($r < 0.45) {
            return random_int(10, 12);
        }

        if ($r < 0.8) {
            return random_int(15, 17);
        }

        return random_int(8, 19);
    }
}
