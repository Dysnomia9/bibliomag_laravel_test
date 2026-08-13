<?php

namespace Tests\Feature;

use App\Models\Libro;
use App\Models\Staff;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrestamoLibroTest extends TestCase
{
    use RefreshDatabase;

    public function test_prestamo_de_libro_con_codigo_no_registrado_devuelve_404(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $response = $this->postJson('/api/prestamos', [
            'usuario_id' => Usuario::factory()->create()->id,
            'tipo_item' => 'libro',
            'codigo_barras' => 'NO-EXISTE',
            'fecha_prestamo' => now()->toDateString(),
            'fecha_devolucion' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(404);
    }

    public function test_prestamo_de_libro_ya_prestado_devuelve_409(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create(['disponible' => false]);

        $response = $this->postJson('/api/prestamos', [
            'usuario_id' => Usuario::factory()->create()->id,
            'tipo_item' => 'libro',
            'codigo_barras' => $libro->codigo_barras,
            'fecha_prestamo' => now()->toDateString(),
            'fecha_devolucion' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(409);
    }

    public function test_prestamo_de_libro_que_no_esta_en_estante_devuelve_409(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create(['estado_proceso' => 'procesos_tecnicos']);

        $response = $this->postJson('/api/prestamos', [
            'usuario_id' => Usuario::factory()->create()->id,
            'tipo_item' => 'libro',
            'codigo_barras' => $libro->codigo_barras,
            'fecha_prestamo' => now()->toDateString(),
            'fecha_devolucion' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(409);
    }

    public function test_prestamo_de_libro_disponible_lo_marca_como_no_disponible_y_guarda_libro_id(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create();
        $usuario = Usuario::factory()->create();

        $response = $this->postJson('/api/prestamos', [
            'usuario_id' => $usuario->id,
            'tipo_item' => 'libro',
            'codigo_barras' => $libro->codigo_barras,
            'fecha_prestamo' => now()->toDateString(),
            'fecha_devolucion' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('libro_id', $libro->id)
            ->assertJsonPath('libro_titulo', $libro->titulo)
            ->assertJsonPath('codigo_barras', $libro->codigo_barras);

        $this->assertDatabaseHas('libros', ['id' => $libro->id, 'disponible' => false]);
    }

    public function test_prestamo_de_libro_sin_fecha_devolucion_devuelve_422(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create();

        $response = $this->postJson('/api/prestamos', [
            'usuario_id' => Usuario::factory()->create()->id,
            'tipo_item' => 'libro',
            'codigo_barras' => $libro->codigo_barras,
            'fecha_prestamo' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }
}
