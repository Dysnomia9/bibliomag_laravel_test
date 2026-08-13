<?php

namespace Tests\Feature;

use App\Models\Libro;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LibroEstadoProcesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cambiar_estado_actualiza_el_libro(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create(['estado_proceso' => 'inventario']);

        $response = $this->patchJson("/api/libros/{$libro->id}/estado", [
            'estado_proceso' => 'en_estante',
        ]);

        $response->assertStatus(200)->assertJsonPath('estado_proceso', 'en_estante');
    }

    public function test_cambiar_estado_a_inventario_estampa_fecha_inventario(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create(['estado_proceso' => 'en_estante', 'fecha_inventario' => null]);

        $response = $this->patchJson("/api/libros/{$libro->id}/estado", [
            'estado_proceso' => 'inventario',
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($libro->refresh()->fecha_inventario);
    }

    public function test_no_se_puede_dar_de_baja_un_libro_prestado_o_reservado(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create(['disponible' => false]);

        $response = $this->patchJson("/api/libros/{$libro->id}/estado", [
            'estado_proceso' => 'de_baja',
        ]);

        $response->assertStatus(409);
        $this->assertNotEquals('de_baja', $libro->refresh()->estado_proceso);
    }

    public function test_se_puede_dar_de_baja_un_libro_disponible(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create(['disponible' => true]);

        $response = $this->patchJson("/api/libros/{$libro->id}/estado", [
            'estado_proceso' => 'de_baja',
        ]);

        $response->assertStatus(200)->assertJsonPath('estado_proceso', 'de_baja');
    }

    public function test_cambiar_estado_con_valor_invalido_devuelve_422(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create();

        $response = $this->patchJson("/api/libros/{$libro->id}/estado", [
            'estado_proceso' => 'no_existe',
        ]);

        $response->assertStatus(422);
    }

    public function test_index_busca_por_titulo_autor_o_codigo_de_barras(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        Libro::factory()->create(['titulo' => 'Cien Años de Soledad', 'autor' => 'García Márquez']);
        Libro::factory()->create(['titulo' => 'Otro Libro', 'autor' => 'Otro Autor']);

        $response = $this->getJson('/api/libros?q=Soledad');

        $response->assertStatus(200)->assertJsonCount(1);
    }

    public function test_buscar_por_codigo_no_encontrado_devuelve_404(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $response = $this->getJson('/api/libros/NO-EXISTE');

        $response->assertStatus(404);
    }

    public function test_buscar_por_codigo_encontrado_devuelve_el_libro(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $libro = Libro::factory()->create();

        $response = $this->getJson("/api/libros/{$libro->codigo_barras}");

        $response->assertStatus(200)->assertJsonPath('id', $libro->id);
    }
}
