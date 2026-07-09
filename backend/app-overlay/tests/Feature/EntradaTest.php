<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Support\Rut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EntradaTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_entrada_con_rut_inexistente_devuelve_404(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $rutInexistente = Rut::formatear(11111111);

        $response = $this->postJson('/api/entrada', ['rut' => $rutInexistente]);

        $response->assertStatus(404);
    }

    public function test_post_entrada_externo_devuelve_201_y_crea_entrada_con_rut_externo(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $rutExterno = Rut::formatear(9999999);

        $response = $this->postJson('/api/entrada/externo', ['rut' => $rutExterno]);

        $response->assertStatus(201)
            ->assertJsonPath('rut_externo', $rutExterno);

        $this->assertDatabaseHas('entradas', ['rut_externo' => $rutExterno]);
    }
}
