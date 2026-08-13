<?php

namespace Tests\Feature;

use App\Models\Libro;
use App\Models\ReservaLibro;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalReservaLibroTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_puede_reservar_un_libro_disponible_por_su_cuenta(): void
    {
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);
        $libro = Libro::factory()->create();

        $response = $this->postJson('/api/mi/reservas-libro', ['codigo_barras' => $libro->codigo_barras]);

        $response->assertStatus(201)->assertJsonPath('estado', 'pendiente');
        $this->assertDatabaseHas('libros', ['id' => $libro->id, 'disponible' => false]);
        $this->assertDatabaseHas('reservas_libro', ['usuario_id' => $usuario->id, 'libro_id' => $libro->id, 'estado' => 'pendiente']);
    }

    public function test_usuario_puede_unirse_a_la_cola_de_un_libro_ocupado_por_su_cuenta(): void
    {
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);
        $libro = Libro::factory()->create(['disponible' => false]);

        $response = $this->postJson('/api/mi/reservas-libro', ['codigo_barras' => $libro->codigo_barras]);

        $response->assertStatus(201)->assertJsonPath('estado', 'en_cola');
    }

    public function test_usuario_ve_su_posicion_en_la_cola_en_su_listado(): void
    {
        $libro = Libro::factory()->create(['disponible' => false]);
        $otro = Usuario::factory()->create();
        Sanctum::actingAs($otro);
        $this->postJson('/api/mi/reservas-libro', ['codigo_barras' => $libro->codigo_barras])->assertStatus(201);

        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);
        $this->postJson('/api/mi/reservas-libro', ['codigo_barras' => $libro->codigo_barras])->assertStatus(201);

        $response = $this->getJson('/api/mi/reservas-libro');

        $response->assertStatus(200);
        $this->assertSame(2, collect($response->json())->firstWhere('usuario_id', $usuario->id)['posicion']);
    }

    public function test_usuario_no_puede_cancelar_la_reserva_de_otro(): void
    {
        $dueno = Usuario::factory()->create();
        $libro = Libro::factory()->create(['disponible' => false]);
        $reserva = ReservaLibro::factory()->create([
            'usuario_id' => $dueno->id,
            'libro_id' => $libro->id,
            'estado' => 'pendiente',
        ]);

        $otroUsuario = Usuario::factory()->create();
        Sanctum::actingAs($otroUsuario);

        $response = $this->patchJson("/api/mi/reservas-libro/{$reserva->id}/cancelar");

        $response->assertStatus(403);
        $this->assertDatabaseHas('reservas_libro', ['id' => $reserva->id, 'estado' => 'pendiente']);
    }

    public function test_usuario_puede_cancelar_su_propia_reserva(): void
    {
        $usuario = Usuario::factory()->create();
        $libro = Libro::factory()->create(['disponible' => false]);
        $reserva = ReservaLibro::factory()->create([
            'usuario_id' => $usuario->id,
            'libro_id' => $libro->id,
            'estado' => 'pendiente',
        ]);

        Sanctum::actingAs($usuario);
        $response = $this->patchJson("/api/mi/reservas-libro/{$reserva->id}/cancelar");

        $response->assertStatus(200)->assertJsonPath('estado', 'cancelado');
        $this->assertDatabaseHas('libros', ['id' => $libro->id, 'disponible' => true]);
    }

    public function test_staff_no_puede_usar_las_rutas_del_portal(): void
    {
        Sanctum::actingAs(\App\Models\Staff::factory()->create());

        $response = $this->getJson('/api/mi/reservas-libro');

        $response->assertStatus(403);
    }
}
