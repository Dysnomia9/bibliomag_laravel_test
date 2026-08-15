<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Sala;
use App\Models\Staff;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalaConfirmacionAsistenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_plazo_de_confirmacion_corre_desde_el_inicio_del_bloque_si_se_reservo_con_anticipacion(): void
    {
        Carbon::setTestNow('2026-08-15 09:00:00');
        $reserva = Reserva::factory()->create(['fecha' => '2026-08-15', 'hora_inicio' => 14, 'hora_fin' => 16]);

        $this->assertSame('2026-08-15 14:15:00', $reserva->plazoConfirmacion()->toDateTimeString());
    }

    public function test_plazo_de_confirmacion_corre_desde_la_reserva_si_se_reservo_ahora_dentro_de_un_bloque_ya_iniciado(): void
    {
        Carbon::setTestNow('2026-08-15 15:00:00');
        $reserva = Reserva::factory()->create(['fecha' => '2026-08-15', 'hora_inicio' => 14, 'hora_fin' => 16]);

        $this->assertSame('2026-08-15 15:15:00', $reserva->plazoConfirmacion()->toDateTimeString());
    }

    public function test_confirmar_llegada_dentro_del_plazo_marca_prestado_por_y_hora(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        Carbon::setTestNow('2026-08-15 14:05:00');
        $reserva = Reserva::factory()->create(['fecha' => '2026-08-15', 'hora_inicio' => 14, 'hora_fin' => 16]);

        $response = $this->patchJson("/api/reservas/{$reserva->id}/llegada", ['registrado_por' => 'Ana Pérez']);

        $response->assertStatus(200)->assertJsonPath('prestado_por', 'Ana Pérez');
        $this->assertNotNull($response->json('hora_prestamo_real'));
    }

    public function test_confirmar_llegada_dos_veces_devuelve_409(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        Carbon::setTestNow('2026-08-15 14:05:00');
        $reserva = Reserva::factory()->create(['fecha' => '2026-08-15', 'hora_inicio' => 14, 'hora_fin' => 16]);

        $this->patchJson("/api/reservas/{$reserva->id}/llegada", ['registrado_por' => 'Ana Pérez'])->assertStatus(200);
        $response = $this->patchJson("/api/reservas/{$reserva->id}/llegada", ['registrado_por' => 'Ana Pérez']);

        $response->assertStatus(409);
    }

    public function test_confirmar_llegada_pasado_el_plazo_devuelve_409_y_libera_la_reserva(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        Carbon::setTestNow('2026-08-15 09:00:00');
        $reserva = Reserva::factory()->create(['fecha' => '2026-08-15', 'hora_inicio' => 14, 'hora_fin' => 16]);

        Carbon::setTestNow('2026-08-15 14:16:00');
        $response = $this->patchJson("/api/reservas/{$reserva->id}/llegada", ['registrado_por' => 'Ana Pérez']);

        $response->assertStatus(409);
        $this->assertSame('no_show', $reserva->fresh()->estado);
    }

    public function test_liberar_reserva_vencida_la_marca_no_show(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        Carbon::setTestNow('2026-08-15 09:00:00');
        $reserva = Reserva::factory()->create(['fecha' => '2026-08-15', 'hora_inicio' => 14, 'hora_fin' => 16]);

        Carbon::setTestNow('2026-08-15 14:20:00');
        $response = $this->patchJson("/api/reservas/{$reserva->id}/liberar");

        $response->assertStatus(200)->assertJsonPath('estado', 'no_show');
    }

    public function test_liberar_reserva_todavia_dentro_del_plazo_devuelve_409(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        Carbon::setTestNow('2026-08-15 14:05:00');
        $reserva = Reserva::factory()->create(['fecha' => '2026-08-15', 'hora_inicio' => 14, 'hora_fin' => 16]);

        $response = $this->patchJson("/api/reservas/{$reserva->id}/liberar");

        $response->assertStatus(409);
    }

    public function test_reservar_el_mismo_bloque_despues_de_vencido_el_plazo_anterior_es_posible(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $sala = Sala::factory()->create();

        Carbon::setTestNow('2026-08-15 09:00:00');
        Reserva::factory()->conParticipantes(Usuario::factory()->count(2)->create())->create([
            'sala_id' => $sala->id,
            'fecha' => '2026-08-15',
            'hora_inicio' => 14,
            'hora_fin' => 16,
            'cantidad_personas' => 2,
        ]);

        Carbon::setTestNow('2026-08-15 14:16:00');
        $nuevos = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-08-15',
            'hora_inicio' => 14,
            'hora_fin' => 16,
            'cantidad_personas' => 2,
            'ruts' => $nuevos->pluck('rut')->all(),
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, Reserva::where('sala_id', $sala->id)->where('estado', 'no_show')->count());
    }

    public function test_index_excluye_no_show_y_expira_perezosamente(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        Carbon::setTestNow('2026-08-15 09:00:00');
        $reserva = Reserva::factory()->create(['fecha' => '2026-08-15', 'hora_inicio' => 14, 'hora_fin' => 16]);

        Carbon::setTestNow('2026-08-15 14:20:00');
        $response = $this->getJson('/api/salas?fecha=2026-08-15');

        $response->assertStatus(200)->assertJsonCount(0, 'reservas');
        $this->assertSame('no_show', $reserva->fresh()->estado);
    }

    public function test_index_expone_plazo_confirmacion_y_vencida_sin_confirmar(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        Carbon::setTestNow('2026-08-15 09:00:00');
        $reserva = Reserva::factory()->create(['fecha' => '2026-08-15', 'hora_inicio' => 14, 'hora_fin' => 16]);

        $response = $this->getJson('/api/salas?fecha=2026-08-15');

        $response->assertStatus(200)->assertJsonPath('reservas.0.vencida_sin_confirmar', false);
        $plazoDevuelto = Carbon::parse($response->json('reservas.0.plazo_confirmacion'));
        $this->assertTrue($plazoDevuelto->equalTo($reserva->plazoConfirmacion()));
    }
}
