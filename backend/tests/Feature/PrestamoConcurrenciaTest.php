<?php

namespace Tests\Feature;

use App\Models\Libro;
use App\Models\ReservaLibro;
use App\Models\Staff;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrestamoConcurrenciaTest extends TestCase
{
    use RefreshDatabase;

    /** @return string[] */
    private function capturarSql(\Closure $accion): array
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $accion();

        DB::flushQueryLog();

        return $queries;
    }

    private function tieneForUpdate(array $queries): bool
    {
        return collect($queries)->contains(fn ($sql) => str_contains(strtolower($sql), 'for update'));
    }

    public function test_prestamo_de_libro_bloquea_la_fila_con_lock_for_update(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create();
        $usuario = Usuario::factory()->create();

        $queries = $this->capturarSql(function () use ($libro, $usuario) {
            $this->postJson('/api/prestamos', [
                'usuario_id' => $usuario->id,
                'tipo_item' => 'libro',
                'codigo_barras' => $libro->codigo_barras,
                'fecha_prestamo' => now()->toDateString(),
                'fecha_devolucion' => now()->addDays(7)->toDateString(),
            ])->assertStatus(201);
        });

        $this->assertTrue($this->tieneForUpdate($queries), 'Se esperaba una query FOR UPDATE al crear el préstamo de libro.');
        $this->assertDatabaseHas('libros', ['id' => $libro->id, 'disponible' => false]);
    }

    public function test_prestamo_de_equipo_bloquea_la_fila_con_lock_for_update(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $equipo = \App\Models\Equipo::factory()->create(['tipo' => 'audifonos']);
        $usuario = Usuario::factory()->create();

        $queries = $this->capturarSql(function () use ($equipo, $usuario) {
            $this->postJson('/api/prestamos', [
                'usuario_id' => $usuario->id,
                'tipo_item' => 'audifonos',
                'libro_titulo' => $equipo->codigo_inventario,
            ])->assertStatus(201);
        });

        $this->assertTrue($this->tieneForUpdate($queries), 'Se esperaba una query FOR UPDATE al crear el préstamo de equipo.');
    }

    public function test_reserva_de_libro_bloquea_la_fila_con_lock_for_update(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create();
        $usuario = Usuario::factory()->create();

        $queries = $this->capturarSql(function () use ($libro, $usuario) {
            $this->postJson('/api/reservas-libro', [
                'usuario_id' => $usuario->id,
                'codigo_barras' => $libro->codigo_barras,
                'fecha_reserva' => now()->toDateString(),
                'fecha_retiro' => now()->addDays(2)->toDateString(),
            ])->assertStatus(201);
        });

        $this->assertTrue($this->tieneForUpdate($queries), 'Se esperaba una query FOR UPDATE al crear la reserva de libro.');
        $this->assertDatabaseHas('libros', ['id' => $libro->id, 'disponible' => false]);
    }

    public function test_cancelar_reserva_de_libro_libera_disponibilidad_dentro_de_transaccion(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create(['disponible' => false]);
        $reserva = ReservaLibro::factory()->create([
            'libro_id' => $libro->id,
            'usuario_id' => Usuario::factory()->create()->id,
            'estado' => 'pendiente',
        ]);

        $this->patchJson("/api/reservas-libro/{$reserva->id}/cancelar")->assertStatus(200);

        $this->assertDatabaseHas('libros', ['id' => $libro->id, 'disponible' => true]);
        $this->assertDatabaseHas('reservas_libro', ['id' => $reserva->id, 'estado' => 'cancelado']);
    }

    public function test_devolver_prestamo_de_libro_libera_disponibilidad_dentro_de_transaccion(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create(['disponible' => false]);
        $prestamo = \App\Models\Prestamo::factory()->create([
            'usuario_id' => Usuario::factory()->create()->id,
            'libro_id' => $libro->id,
            'tipo_item' => 'libro',
            'fecha_devolucion' => now()->addDay(),
        ]);

        $this->patchJson("/api/prestamos/{$prestamo->id}/devolver")->assertStatus(200);

        $this->assertDatabaseHas('libros', ['id' => $libro->id, 'disponible' => true]);
    }
}
