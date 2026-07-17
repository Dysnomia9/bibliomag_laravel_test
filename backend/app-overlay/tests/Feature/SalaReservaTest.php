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

    public function test_participante_sin_reserva_solapada_permite_reservar(): void
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

        $response->assertStatus(201);
    }
}
