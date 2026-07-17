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
}
