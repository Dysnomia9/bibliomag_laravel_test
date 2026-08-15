<?php

namespace Tests\Feature;

use App\Models\Ejemplar;
use App\Models\Libro;
use App\Models\Prestamo;
use App\Models\Staff;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffAtribucionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_prestamo_estampa_el_staff_autenticado_sin_pedirlo_en_el_body(): void
    {
        $staffA = Staff::factory()->create(['nombre' => 'Ana Pérez']);
        Sanctum::actingAs($staffA);

        $ejemplar = Ejemplar::factory()->for(Libro::factory())->create();
        $usuario = Usuario::factory()->create();

        $response = $this->postJson('/api/prestamos', [
            'usuario_id' => $usuario->id,
            'tipo_item' => 'libro',
            'codigo_barras' => $ejemplar->codigo_barras,
            'fecha_prestamo' => now()->toDateString(),
            'fecha_devolucion' => now()->addDays(7)->toDateString(),
            // Un intento de suplantar a otro staff vía el body no debe tener efecto.
            'prestado_por' => 'Alguien Más',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('prestado_por', 'Ana Pérez')
            ->assertJsonPath('prestado_por_staff_id', $staffA->id);
    }

    public function test_devolver_prestamo_estampa_el_staff_autenticado(): void
    {
        $staffB = Staff::factory()->create(['nombre' => 'Bruno Ríos']);
        Sanctum::actingAs($staffB);

        $prestamo = Prestamo::factory()->create([
            'usuario_id' => Usuario::factory()->create()->id,
            'tipo_item' => 'audifonos',
            'fecha_devolucion' => null,
            'estado' => 'activo',
        ]);

        $response = $this->patchJson("/api/prestamos/{$prestamo->id}/devolver");

        $response->assertStatus(200)
            ->assertJsonPath('devuelto_por', 'Bruno Ríos')
            ->assertJsonPath('devuelto_por_staff_id', $staffB->id);
    }
}
