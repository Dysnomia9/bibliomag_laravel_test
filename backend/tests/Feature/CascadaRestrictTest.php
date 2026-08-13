<?php

namespace Tests\Feature;

use App\Models\Entrada;
use App\Models\Equipo;
use App\Models\Libro;
use App\Models\Prestamo;
use App\Models\ReservaLibro;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CascadaRestrictTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_se_puede_borrar_un_usuario_con_prestamos(): void
    {
        $usuario = Usuario::factory()->create();
        Prestamo::factory()->create(['usuario_id' => $usuario->id]);

        $this->expectException(QueryException::class);
        $usuario->delete();
    }

    public function test_no_se_puede_borrar_un_usuario_con_entradas(): void
    {
        $usuario = Usuario::factory()->create();
        Entrada::factory()->create(['usuario_id' => $usuario->id]);

        $this->expectException(QueryException::class);
        $usuario->delete();
    }

    public function test_no_se_puede_borrar_un_usuario_con_reservas_de_libro(): void
    {
        $usuario = Usuario::factory()->create();
        ReservaLibro::factory()->create(['usuario_id' => $usuario->id]);

        $this->expectException(QueryException::class);
        $usuario->delete();
    }

    public function test_no_se_puede_borrar_un_libro_con_prestamos(): void
    {
        $libro = Libro::factory()->create();
        Prestamo::factory()->create(['libro_id' => $libro->id, 'tipo_item' => 'libro']);

        $this->expectException(QueryException::class);
        $libro->delete();
    }

    public function test_no_se_puede_borrar_un_libro_con_reservas(): void
    {
        $libro = Libro::factory()->create();
        ReservaLibro::factory()->create(['libro_id' => $libro->id]);

        $this->expectException(QueryException::class);
        $libro->delete();
    }

    public function test_no_se_puede_borrar_un_equipo_con_prestamos(): void
    {
        $equipo = Equipo::factory()->create();
        Prestamo::factory()->create(['equipo_id' => $equipo->id, 'tipo_item' => $equipo->tipo, 'libro_id' => null]);

        $this->expectException(QueryException::class);
        $equipo->delete();
    }

    public function test_se_puede_borrar_un_usuario_sin_historial_asociado(): void
    {
        $usuario = Usuario::factory()->create();

        $usuario->delete();

        $this->assertDatabaseMissing('usuarios', ['id' => $usuario->id]);
    }
}
