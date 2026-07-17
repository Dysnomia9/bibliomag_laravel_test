<?php

namespace App\Console\Commands;

use App\Models\Entrada;
use App\Models\Equipo;
use App\Models\Libro;
use App\Models\Prestamo;
use App\Models\Reserva;
use App\Models\ReservaLibro;
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

    /** Catálogo completo de biblioteca (título/autor/área) para el portal de usuario — adaptado de DEPRECATED/catalogo/page.tsx */
    private array $catalogoLibros = [
        ['codigo' => '9789561228351', 'titulo' => 'Introducción a la Programación en Python', 'autor' => 'John V. Guttag', 'categoria' => 'Computación'],
        ['codigo' => '9789706868824', 'titulo' => 'Cálculo Diferencial e Integral - Stewart', 'autor' => 'James Stewart', 'categoria' => 'Matemáticas'],
        ['codigo' => '9786073237826', 'titulo' => 'Física Universitaria - Sears & Zemansky', 'autor' => 'Hugh D. Young', 'categoria' => 'Física'],
        ['codigo' => '9786071509789', 'titulo' => 'Álgebra Lineal - Grossman', 'autor' => 'Stanley Grossman', 'categoria' => 'Matemáticas'],
        ['codigo' => '9786071513939', 'titulo' => 'Química General - Chang', 'autor' => 'Raymond Chang', 'categoria' => 'Química'],
        ['codigo' => '9789563160215', 'titulo' => 'Historia de la Patagonia', 'autor' => 'Mateo Martinic', 'categoria' => 'Historia'],
        ['codigo' => '9789562827164', 'titulo' => 'Botánica Austral', 'autor' => 'Carlos Zöllner', 'categoria' => 'Biología'],
        ['codigo' => '9789561224100', 'titulo' => 'Derecho Constitucional Chileno', 'autor' => 'Humberto Nogueira', 'categoria' => 'Derecho'],
        ['codigo' => '9789562014533', 'titulo' => 'Enfermería Comunitaria', 'autor' => 'Marcia Padilla', 'categoria' => 'Enfermería'],
        ['codigo' => '9789561420311', 'titulo' => 'Ecología y Medio Ambiente', 'autor' => 'Eugene Odum', 'categoria' => 'Biología'],
        ['titulo' => 'Estructuras de Datos y Algoritmos', 'autor' => 'Robert Sedgewick', 'categoria' => 'Computación'],
        ['titulo' => 'Redes de Computadores', 'autor' => 'Andrew Tanenbaum', 'categoria' => 'Computación'],
        ['titulo' => 'Ingeniería de Software', 'autor' => 'Ian Sommerville', 'categoria' => 'Computación'],
        ['titulo' => 'Bases de Datos', 'autor' => 'Abraham Silberschatz', 'categoria' => 'Computación'],
        ['titulo' => 'Cálculo I', 'autor' => 'Michael Spivak', 'categoria' => 'Matemáticas'],
        ['titulo' => 'Probabilidad y Estadística', 'autor' => 'Ronald Walpole', 'categoria' => 'Matemáticas'],
        ['titulo' => 'Macroeconomía', 'autor' => 'Gregory Mankiw', 'categoria' => 'Economía'],
        ['titulo' => 'Microeconomía Intermedia', 'autor' => 'Hal Varian', 'categoria' => 'Economía'],
        ['titulo' => 'Contabilidad General', 'autor' => 'Charles Horngren', 'categoria' => 'Economía'],
        ['titulo' => 'Introducción al Derecho Civil', 'autor' => 'Arturo Alessandri', 'categoria' => 'Derecho'],
        ['titulo' => 'Derecho Procesal Penal', 'autor' => 'Cristian Maturana', 'categoria' => 'Derecho'],
        ['titulo' => 'Anatomía y Fisiología', 'autor' => 'Gerard Tortora', 'categoria' => 'Enfermería'],
        ['titulo' => 'Farmacología Clínica', 'autor' => 'Bertram Katzung', 'categoria' => 'Enfermería'],
        ['titulo' => 'Fundamentos de Trabajo Social', 'autor' => 'Ezequiel Ander-Egg', 'categoria' => 'Trabajo Social'],
        ['titulo' => 'Psicología Social', 'autor' => 'David Myers', 'categoria' => 'Psicología'],
        ['titulo' => 'Didáctica General', 'autor' => 'Alicia Camilloni', 'categoria' => 'Educación'],
        ['titulo' => 'Psicología del Desarrollo', 'autor' => 'Jean Piaget', 'categoria' => 'Educación'],
        ['titulo' => 'Producción Animal', 'autor' => 'Ricardo Bocco', 'categoria' => 'Medicina Veterinaria'],
        ['titulo' => 'Patología Veterinaria', 'autor' => 'M. Donald McGavin', 'categoria' => 'Medicina Veterinaria'],
        ['titulo' => 'Resistencia de Materiales', 'autor' => 'Ferdinand Beer', 'categoria' => 'Construcción Civil'],
        ['titulo' => 'Hormigón Armado', 'autor' => 'Jack McCormac', 'categoria' => 'Construcción Civil'],
        ['titulo' => 'Historia de Chile Contemporáneo', 'autor' => 'Alfredo Jocelyn-Holt', 'categoria' => 'Historia'],
        ['titulo' => 'Geografía de Magallanes', 'autor' => 'Mateo Martinic', 'categoria' => 'Historia'],
        ['titulo' => 'Biología Molecular de la Célula', 'autor' => 'Bruce Alberts', 'categoria' => 'Biología'],
    ];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->info('Eliminando datos de prueba existentes...');
            DB::table('reservas_libro')->delete();
            DB::table('libros')->delete();
            DB::table('reserva_participantes')->delete();
            DB::table('reservas')->delete();
            DB::table('entradas')->delete();
            DB::table('equipos')->delete();
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
        $equipos = $this->seedEquipos();
        $this->seedPrestamos($usuarios, $equipos);
        $salas = $this->seedSalas();
        $this->seedReservas($salas, $usuarios);
        $libros = $this->seedLibros();
        $this->seedReservasLibro($libros, $usuarios);

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
                'password' => Hash::make('umag123'),
                'tipo' => $this->tiposUsuario[array_rand($this->tiposUsuario)],
                'carrera' => $this->carreras[array_rand($this->carreras)],
                'anio_ingreso' => $this->aniosIngreso[array_rand($this->aniosIngreso)],
                'sexo' => $this->sexos[array_rand($this->sexos)],
                'activo' => true,
                'qr_code' => strtoupper(uniqid('QR')),
            ]));
        }

        $this->line("  · {$cantidad} usuarios creados (con carrera, año de ingreso, sexo y clave de portal: umag123)");

        return $usuarios;
    }

    private function seedEntradas($usuarios): void
    {
        $total = 0;
        $horaActual = now()->hour;

        foreach ($usuarios as $i => $usuario) {
            // Cada usuario tiene entre 1 y 4 entradas distribuidas en los últimos 14 días
            $numEntradas = random_int(1, 4);

            for ($j = 0; $j < $numEntradas; $j++) {
                $diasAtras = random_int(0, 13);
                $hora = $diasAtras === 0 ? random_int(8, max(8, $horaActual)) : $this->horaConSesgo();
                $entrada = now()->subDays($diasAtras)->setTime($hora, random_int(0, 59));
                $conSalida = $diasAtras > 0 && random_int(0, 100) < 70;

                Entrada::create([
                    'usuario_id' => $usuario->id,
                    'fecha_hora_entrada' => $entrada,
                    'fecha_hora_salida' => $conSalida ? $entrada->copy()->addMinutes(random_int(30, 180)) : null,
                    'via' => random_int(0, 100) < 35 ? 'qr' : 'manual',
                    'codigo_barras' => config('horizon_barcodes.puesto_generico'),
                ]);
                $total++;
            }
        }

        // Garantiza una porción de entradas de hoy, para que "Registro de Entrada"
        // no se vea vacío justo tras correr el seed en cualquier momento del día.
        foreach ($usuarios->random(min(12, $usuarios->count())) as $usuario) {
            $hora = random_int(8, max(8, $horaActual));
            $entrada = now()->setTime($hora, random_int(0, 59));

            Entrada::create([
                'usuario_id' => $usuario->id,
                'fecha_hora_entrada' => $entrada,
                'fecha_hora_salida' => random_int(0, 100) < 60 ? $entrada->copy()->addMinutes(random_int(30, 180)) : null,
                'via' => random_int(0, 100) < 35 ? 'qr' : 'manual',
                'codigo_barras' => config('horizon_barcodes.puesto_generico'),
            ]);
            $total++;
        }

        // Un par de visitantes externos y de convenio para hoy, para que las etiquetas
        // "Externo"/"Convenio" del historial tengan ejemplos reales tras el seed.
        foreach ([false, false, true, true] as $esConvenio) {
            $hora = random_int(8, max(8, $horaActual));
            $entrada = now()->setTime($hora, random_int(0, 59));
            $rutVisitante = $this->formatearRut(random_int(9000000, 9999999));

            Entrada::create([
                'rut_externo' => $rutVisitante,
                'nombre_externo' => $this->nombres[array_rand($this->nombres)].' '.$this->apellidos[array_rand($this->apellidos)],
                'es_convenio' => $esConvenio,
                'fecha_hora_entrada' => $entrada,
                'fecha_hora_salida' => random_int(0, 100) < 60 ? $entrada->copy()->addMinutes(random_int(30, 180)) : null,
                'via' => 'manual',
                'codigo_barras' => config('horizon_barcodes.puesto_generico'),
            ]);
            $total++;
        }

        $this->line("  · {$total} entradas creadas (últimos 14 días, con refuerzo para hoy)");
    }

    private function seedPrestamos($usuarios, $equipos): void
    {
        $total = 0;
        $horaActual = now()->hour;

        foreach ($usuarios as $i => $usuario) {
            if (random_int(0, 100) > 60) {
                continue; // no todos los usuarios tienen préstamos
            }

            $numPrestamos = random_int(1, 2);

            for ($j = 0; $j < $numPrestamos; $j++) {
                $diasAtras = random_int(0, 20);
                $hora = $diasAtras === 0 ? random_int(8, max(8, $horaActual)) : $this->horaConSesgo();
                $fechaPrestamo = now()->subDays($diasAtras)->setTime($hora, random_int(0, 59));
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

        // Préstamos de equipos (audífonos y notebooks): se identifican por código de
        // inventario real (tabla `equipos`), no tienen fecha de vencimiento — se
        // devuelven al término de la estadía en la biblioteca. Se limita el sorteo a
        // equipos realmente disponibles del tipo elegido para no simular dos préstamos
        // activos simultáneos del mismo código físico.
        foreach ($usuarios->random(min(10, $usuarios->count())) as $usuario) {
            $tipo = random_int(0, 1) === 0 ? 'audifonos' : 'notebook';
            $disponibles = $equipos->where('tipo', $tipo)->where('disponible', true);

            if ($disponibles->isEmpty()) {
                continue;
            }

            $equipo = $disponibles->random();
            $diasAtras = random_int(0, 5);
            $hora = $diasAtras === 0 ? random_int(8, max(8, $horaActual)) : $this->horaConSesgo();
            $fechaPrestamo = now()->subDays($diasAtras)->setTime($hora, random_int(0, 59));
            $devuelto = $diasAtras > 0 || random_int(0, 100) < 50;

            Prestamo::create([
                'usuario_id' => $usuario->id,
                'equipo_id' => $equipo->id,
                'libro_titulo' => $equipo->codigo_inventario,
                'tipo_item' => $tipo,
                'fecha_prestamo' => $fechaPrestamo,
                'fecha_devolucion' => null,
                'fecha_devolucion_real' => $devuelto ? $fechaPrestamo->copy()->addHours(random_int(1, 6)) : null,
                'estado' => $devuelto ? 'devuelto' : 'activo',
            ]);

            if (! $devuelto) {
                $equipo->update(['disponible' => false]);
            }

            $total++;
        }

        $this->line("  · {$total} préstamos creados (libros y equipos)");
    }

    /** @return \Illuminate\Support\Collection<int, Equipo> */
    private function seedEquipos()
    {
        $codigos = [
            'audifonos' => ['AUD-001', 'AUD-002', 'AUD-003', 'AUD-004'],
            'notebook' => ['NB-001', 'NB-002', 'NB-003'],
        ];

        $equipos = collect();

        foreach ($codigos as $tipo => $codigosTipo) {
            foreach ($codigosTipo as $codigo) {
                $equipos->push(Equipo::create([
                    'codigo_inventario' => $codigo,
                    'tipo' => $tipo,
                    'disponible' => true,
                    'activo' => true,
                ]));
            }
        }

        $this->line("  · {$equipos->count()} equipos creados (audífonos y notebooks)");

        return $equipos;
    }

    /** @return \Illuminate\Support\Collection<int, Sala> */
    private function seedSalas()
    {
        // 25 logias de estudio repartidas en 2 pisos — adaptado de app/staff/salas/page.tsx
        $capacidades = [2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3, 4, 2, 3, 4, 2];
        $salas = collect();

        for ($i = 0; $i < 25; $i++) {
            $salas->push(Sala::create([
                'nombre' => 'Logia '.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'capacidad' => $capacidades[$i],
                'piso' => $i < 13 ? '1er Piso' : '2do Piso',
                'tipo' => 'logia',
                // Código de barras hardcodeado por logia (Horizon aún no entrega los
                // reales) — cada logia tiene el suyo, a diferencia del puesto de
                // trabajo que reutiliza uno genérico.
                'codigo_barras' => (string) (90001 + $i),
            ]));
        }

        $this->line('  · 25 logias de estudio creadas (1er y 2do piso)');

        return $salas;
    }

    private function seedReservas($salas, $usuarios): void
    {
        $bloques = [
            [8, 10], [10, 12], [12, 14], [14, 16], [16, 18], [18, 20], [20, 21],
        ];

        $total = 0;

        // Reservas para hoy y algunos días recientes (no solo "hoy"), para que la
        // demo siga viéndose poblada aunque pasen días sin volver a correr el seed.
        foreach (range(0, 3) as $diasAtras) {
            $fecha = now()->subDays($diasAtras)->toDateString();

            foreach ($salas as $sala) {
                foreach ($bloques as [$inicio, $fin]) {
                    if (random_int(0, 100) > 40) {
                        continue;
                    }

                    $cantidadPersonas = random_int(2, 5);
                    $participantes = $usuarios->random(min($cantidadPersonas, $usuarios->count()))->values();

                    $reserva = Reserva::create([
                        'sala_id' => $sala->id,
                        'usuario_id' => $participantes->first()->id,
                        'rut_usuario' => $participantes->first()->rut,
                        'cantidad_personas' => $cantidadPersonas,
                        'fecha' => $fecha,
                        'hora_inicio' => $inicio,
                        'hora_fin' => $fin,
                        'estado' => 'activa',
                    ]);

                    $reserva->participantes()->attach($participantes->pluck('id'));
                    $total++;
                }
            }
        }

        $this->line("  · {$total} reservas de logia creadas (hoy y últimos 3 días)");
    }

    /** @return \Illuminate\Support\Collection<int, Libro> */
    private function seedLibros()
    {
        $libros = collect();

        foreach ($this->catalogoLibros as $i => $item) {
            $codigo = $item['codigo'] ?? ('978'.str_pad((string) (900000000 + $i), 10, '0', STR_PAD_LEFT));

            $libros->push(Libro::create([
                'codigo_barras' => $codigo,
                'titulo' => $item['titulo'],
                'autor' => $item['autor'],
                'categoria' => $item['categoria'],
                'disponible' => random_int(0, 100) < 78,
                // Los libros de prueba ya están catalogados y en estante: si no, ninguno
                // sería prestable/reservable (PrestamoController/ReservaLibroController
                // exigen estado_proceso = 'en_estante') y las demos/otros seeds quedarían rotos.
                'estado_proceso' => 'en_estante',
            ]));
        }

        $this->line('  · '.$libros->count().' libros creados en el catálogo (con autor, área y disponibilidad)');

        return $libros;
    }

    private function seedReservasLibro($libros, $usuarios): void
    {
        $total = 0;

        foreach ($libros->random(min(4, $libros->count())) as $libro) {
            $usuario = $usuarios->random();
            $fechaReserva = now()->subDays(random_int(0, 5));
            $fechaRetiro = $fechaReserva->copy()->addDays(4);

            ReservaLibro::create([
                'usuario_id' => $usuario->id,
                'libro_id' => $libro->id,
                'fecha_reserva' => $fechaReserva->toDateString(),
                'fecha_retiro' => $fechaRetiro->toDateString(),
                'estado' => $fechaRetiro->isPast() ? 'retirado' : 'pendiente',
            ]);
            $total++;
        }

        $this->line("  · {$total} reservas de libro creadas");
    }

    /** Genera un RUT chileno válido con dígito verificador a partir de un número base */
    private function formatearRut(int $numero): string
    {
        return Rut::formatear($numero);
    }

    /** Horario biblioteca 8-21 hrs */
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
