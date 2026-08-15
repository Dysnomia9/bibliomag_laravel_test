<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Sala;
use App\Models\Staff;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalaReservaTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_reserva_en_bloque_ya_ocupado_devuelve_409(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $sala = Sala::factory()->create();
        $ocupantes = Usuario::factory()->count(2)->create();

        Reserva::factory()->conParticipantes($ocupantes)->create([
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
        ]);

        $nuevos = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
            'ruts' => $nuevos->pluck('rut')->all(),
        ]);

        $response->assertStatus(409);
    }

    public function test_reserva_grupal_con_cantidad_de_ruts_distinta_a_cantidad_personas_devuelve_422(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $sala = Sala::factory()->create();
        $usuarios = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 14,
            'hora_fin' => 16,
            'cantidad_personas' => 3,
            'ruts' => $usuarios->pluck('rut')->all(),
        ]);

        $response->assertStatus(422);
    }

    public function test_participante_con_reserva_en_otra_sala_en_horario_solapado_devuelve_409(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $salaOcupada = Sala::factory()->create();
        $salaNueva = Sala::factory()->create();
        $compartido = Usuario::factory()->create();
        $otroOcupante = Usuario::factory()->create();

        Reserva::factory()->conParticipantes([$compartido, $otroOcupante])->create([
            'sala_id' => $salaOcupada->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
        ]);

        $nuevos = Usuario::factory()->count(1)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $salaNueva->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 11,
            'hora_fin' => 13,
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $nuevos->first()->rut],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', "El RUT {$compartido->rut} ya tiene otra sala reservada en ese horario");
    }

    /**
     * Antes esto se permitía porque los bloques 10-12 y 12-14 no se solapan en el
     * tiempo — pero la regla de "un bloque a la vez, salvo extensión en la MISMA
     * sala" (participanteExcedeLimiteDeBloques) es más estricta que solo el
     * solapamiento horario: una sala DISTINTA nunca cuenta como extensión válida,
     * aunque el horario calce perfecto.
     */
    public function test_participante_con_reserva_activa_en_otra_sala_no_puede_reservar_aunque_no_se_solape(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $salaOcupada = Sala::factory()->create();
        $salaNueva = Sala::factory()->create();
        $compartido = Usuario::factory()->create();
        $otroOcupante = Usuario::factory()->create();

        Reserva::factory()->conParticipantes([$compartido, $otroOcupante])->create([
            'sala_id' => $salaOcupada->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
        ]);

        $nuevos = Usuario::factory()->count(1)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $salaNueva->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 12,
            'hora_fin' => 14,
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $nuevos->first()->rut],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', "El RUT {$compartido->rut} ya tiene una reserva activa en otra sala hoy — solo puede reservar el bloque anterior o siguiente en la misma sala.");
    }

    public function test_participante_puede_extender_al_bloque_siguiente_de_la_misma_sala(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $sala = Sala::factory()->create();
        $compartido = Usuario::factory()->create();
        $otro = Usuario::factory()->create();

        Reserva::factory()->conParticipantes([$compartido, $otro])->create([
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
        ]);

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 12,
            'hora_fin' => 14,
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $otro->rut],
        ]);

        $response->assertStatus(201);
    }

    public function test_participante_puede_extender_al_bloque_anterior_de_la_misma_sala(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $sala = Sala::factory()->create();
        $compartido = Usuario::factory()->create();
        $otro = Usuario::factory()->create();

        Reserva::factory()->conParticipantes([$compartido, $otro])->create([
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 12,
            'hora_fin' => 14,
            'cantidad_personas' => 2,
        ]);

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $otro->rut],
        ]);

        $response->assertStatus(201);
    }

    public function test_participante_no_puede_reservar_bloque_no_adyacente_de_la_misma_sala(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $sala = Sala::factory()->create();
        $compartido = Usuario::factory()->create();
        $otro = Usuario::factory()->create();

        Reserva::factory()->conParticipantes([$compartido, $otro])->create([
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
        ]);

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 14,
            'hora_fin' => 16,
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $otro->rut],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', "El RUT {$compartido->rut} ya tiene una reserva activa en esta sala en un bloque no consecutivo — solo puede agregar el bloque inmediatamente anterior o siguiente.");
    }

    public function test_participante_no_puede_extender_en_ambas_direcciones_a_la_vez(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $sala = Sala::factory()->create();
        $compartido = Usuario::factory()->create();
        $otro = Usuario::factory()->create();

        // Ya tiene 10-12 y 12-14 (una extensión hacia adelante ya usada).
        Reserva::factory()->conParticipantes([$compartido, $otro])->create([
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
        ]);
        Reserva::factory()->conParticipantes([$compartido, $otro])->create([
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 12,
            'hora_fin' => 14,
            'cantidad_personas' => 2,
        ]);

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 8,
            'hora_fin' => 10,
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $otro->rut],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', "El RUT {$compartido->rut} ya alcanzó el máximo de bloques reservados por hoy (2, en la misma sala).");
    }

    /** Una reserva finalizada (llave ya devuelta) no cuenta contra el límite — libera al participante para reservar de nuevo ese mismo día. */
    public function test_reserva_finalizada_no_cuenta_para_el_limite_de_bloques(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $salaVieja = Sala::factory()->create();
        $salaNueva = Sala::factory()->create();
        $compartido = Usuario::factory()->create();
        $otro = Usuario::factory()->create();

        Reserva::factory()->conParticipantes([$compartido, $otro])->create([
            'sala_id' => $salaVieja->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 8,
            'hora_fin' => 10,
            'cantidad_personas' => 2,
            'estado' => 'finalizada',
        ]);

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $salaNueva->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 14,
            'hora_fin' => 16,
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $otro->rut],
        ]);

        $response->assertStatus(201);
    }
}
