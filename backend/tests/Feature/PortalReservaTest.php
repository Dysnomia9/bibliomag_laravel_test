<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Sala;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalReservaTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_no_puede_cancelar_reserva_que_no_le_pertenece(): void
    {
        $dueno = Usuario::factory()->create();
        $otroUsuario = Usuario::factory()->create();
        $sala = Sala::factory()->create();

        $reserva = Reserva::factory()->conParticipantes([$dueno])->create([
            'sala_id' => $sala->id,
            'usuario_id' => $dueno->id,
            'rut_usuario' => $dueno->rut,
            'cantidad_personas' => 1,
        ]);

        Sanctum::actingAs($otroUsuario);

        $response = $this->deleteJson("/api/mi/reservas/{$reserva->id}");

        // PortalController::cancelarReservaSala compara $reserva->usuario_id contra
        // $request->user()->id y devuelve 403 (no 404) cuando no coinciden.
        $response->assertStatus(403);

        $this->assertDatabaseHas('reservas', ['id' => $reserva->id]);
    }

    public function test_usuario_no_puede_reservar_para_un_dia_futuro(): void
    {
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);
        $sala = Sala::factory()->create();

        $response = $this->postJson('/api/mi/reservas', [
            'sala_id' => $sala->id,
            'fecha' => now()->addDay()->toDateString(),
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
            'ruts' => [$usuario->rut, Usuario::factory()->create()->rut],
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'Solo puedes reservar una sala para el día de hoy');
        $this->assertDatabaseMissing('reservas', ['sala_id' => $sala->id]);
    }

    public function test_usuario_puede_reservar_para_el_dia_de_hoy(): void
    {
        $usuario = Usuario::factory()->create();
        Sanctum::actingAs($usuario);
        $sala = Sala::factory()->create();
        $otro = Usuario::factory()->create();

        $response = $this->postJson('/api/mi/reservas', [
            'sala_id' => $sala->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
            'ruts' => [$usuario->rut, $otro->rut],
        ]);

        $response->assertStatus(201);
    }

    public function test_usuario_no_puede_reservar_otra_sala_si_ya_tiene_una_activa(): void
    {
        $usuario = Usuario::factory()->create();
        $otro = Usuario::factory()->create();
        $salaVieja = Sala::factory()->create();
        $salaNueva = Sala::factory()->create();

        Reserva::factory()->conParticipantes([$usuario, $otro])->create([
            'sala_id' => $salaVieja->id,
            'usuario_id' => $usuario->id,
            'rut_usuario' => $usuario->rut,
            'fecha' => now()->toDateString(),
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/mi/reservas', [
            'sala_id' => $salaNueva->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => 14,
            'hora_fin' => 16,
            'cantidad_personas' => 2,
            'ruts' => [$usuario->rut, $otro->rut],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Ya tienes una reserva activa en otra sala hoy — solo puedes reservar el bloque anterior o siguiente en la misma sala.');
    }

    public function test_usuario_puede_extender_a_bloque_adyacente_de_la_misma_sala(): void
    {
        $usuario = Usuario::factory()->create();
        $otro = Usuario::factory()->create();
        $sala = Sala::factory()->create();

        Reserva::factory()->conParticipantes([$usuario, $otro])->create([
            'sala_id' => $sala->id,
            'usuario_id' => $usuario->id,
            'rut_usuario' => $usuario->rut,
            'fecha' => now()->toDateString(),
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/mi/reservas', [
            'sala_id' => $sala->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => 12,
            'hora_fin' => 14,
            'cantidad_personas' => 2,
            'ruts' => [$usuario->rut, $otro->rut],
        ]);

        $response->assertStatus(201);
    }
}
