<?php

namespace Tests\Feature;

use App\Models\Libro;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LibroCatalogacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_se_puede_catalogar_un_libro_con_codigo_de_barras_ya_usado(): void
    {
        Sanctum::actingAs(Staff::factory()->create(['rol' => 'admin']));

        $existente = Libro::factory()->create(['codigo_barras' => '7501234567890']);

        $response = $this->postJson('/api/libros', [
            'codigo_barras' => $existente->codigo_barras,
            'titulo' => 'Otro título cualquiera',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('codigo_barras');

        $this->assertSame(1, Libro::where('codigo_barras', $existente->codigo_barras)->count());
    }

    public function test_no_se_puede_actualizar_un_libro_al_codigo_de_barras_de_otro(): void
    {
        Sanctum::actingAs(Staff::factory()->create(['rol' => 'admin']));

        $libroA = Libro::factory()->create(['codigo_barras' => '7501234567890']);
        $libroB = Libro::factory()->create(['codigo_barras' => '7509876543210']);

        $response = $this->patchJson("/api/libros/{$libroB->id}", [
            'codigo_barras' => $libroA->codigo_barras,
            'titulo' => $libroB->titulo,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('codigo_barras');
    }

    public function test_se_puede_actualizar_un_libro_manteniendo_su_propio_codigo_de_barras(): void
    {
        Sanctum::actingAs(Staff::factory()->create(['rol' => 'admin']));

        $libro = Libro::factory()->create(['codigo_barras' => '7501234567890']);

        $response = $this->patchJson("/api/libros/{$libro->id}", [
            'codigo_barras' => $libro->codigo_barras,
            'titulo' => 'Título actualizado',
        ]);

        $response->assertStatus(200)->assertJsonPath('titulo', 'Título actualizado');
    }

    public function test_codigo_de_barras_duplicado_falla_a_nivel_de_base_de_datos(): void
    {
        Libro::factory()->create(['codigo_barras' => '7501234567890']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Libro::create([
            'codigo_barras' => '7501234567890',
            'titulo' => 'Otro libro',
        ]);
    }
}
