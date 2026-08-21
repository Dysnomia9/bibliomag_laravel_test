<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Sala;
use App\Models\Staff;
use App\Models\Usuario;
use App\Services\ReservaSalaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalaReservaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_crear_reserva_en_tramo_ya_ocupado_devuelve_409(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $sala = Sala::factory()->create();
        $ocupantes = Usuario::factory()->count(2)->create();

        Reserva::factory()->conParticipantes($ocupantes)->create([
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
            'cantidad_personas' => 2,
        ]);

        $nuevos = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
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
            'hora_inicio' => '14:00',
            'hora_fin' => '16:00',
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
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
            'cantidad_personas' => 2,
        ]);

        $nuevos = Usuario::factory()->count(1)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $salaNueva->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '11:00',
            'hora_fin' => '13:00',
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $nuevos->first()->rut],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', "El RUT {$compartido->rut} ya tiene otra sala reservada en ese horario");
    }

    /**
     * Antes esto se rechazaba porque un mismo participante no podía tener reservas
     * 'activa' en salas distintas el mismo día (regla de adyacencia/misma-sala) — esa
     * regla se eliminó con el horario continuo (ver R6 en la spec): ahora lo único que
     * importa es la cuota diaria de minutos, sin importar en cuántas salas distintas
     * se reparten, siempre que los horarios no se solapen entre sí.
     */
    public function test_reservas_encadenadas_del_mismo_usuario_en_distinta_sala_sin_solape_son_validas(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $salaA = Sala::factory()->create();
        $salaB = Sala::factory()->create();
        $compartido = Usuario::factory()->create();
        $otro = Usuario::factory()->create();

        Reserva::factory()->conParticipantes([$compartido, $otro])->create([
            'sala_id' => $salaA->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '15:00',
            'hora_fin' => '17:00',
            'cantidad_personas' => 2,
        ]);

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $salaB->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '17:00',
            'hora_fin' => '19:00',
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $otro->rut],
        ]);

        $response->assertStatus(201);
    }

    /** R6: una reserva 'finalizada' (llave ya devuelta) sí consume cuota diaria — a diferencia de la vieja regla de "límite de bloques", que la excluía por completo. */
    public function test_reserva_finalizada_cuenta_para_la_cuota_diaria(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $salaVieja = Sala::factory()->create();
        $salaNueva = Sala::factory()->create();
        $compartido = Usuario::factory()->create();
        $otro = Usuario::factory()->create();

        // 3 h 45 min ya usadas y finalizadas.
        Reserva::factory()->conParticipantes([$compartido, $otro])->create([
            'sala_id' => $salaVieja->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '08:00',
            'hora_fin' => '11:45',
            'cantidad_personas' => 2,
            'estado' => 'finalizada',
        ]);

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $salaNueva->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '14:00',
            'hora_fin' => '14:30',
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $otro->rut],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', "El RUT {$compartido->rut} ya tiene 3 h 45 min reservados hoy; el máximo diario es de 4 h.");
    }

    public function test_reserva_de_1540_a_1740_en_sala_libre_devuelve_201(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        Carbon::setTestNow('2026-07-10 15:40:00');
        $sala = Sala::factory()->create();
        $usuarios = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '15:40',
            'hora_fin' => '17:40',
            'cantidad_personas' => 2,
            'ruts' => $usuarios->pluck('rut')->all(),
            'inmediata' => true,
        ]);

        $response->assertStatus(201);
    }

    public function test_reserva_de_1540_a_1740_con_sala_ocupada_desde_las_1600_devuelve_409(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        Carbon::setTestNow('2026-07-10 15:40:00');
        $sala = Sala::factory()->create();

        Reserva::factory()->conParticipantes(Usuario::factory()->count(2)->create())->create([
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '16:00',
            'hora_fin' => '18:00',
            'cantidad_personas' => 2,
        ]);

        $usuarios = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '15:40',
            'hora_fin' => '17:40',
            'cantidad_personas' => 2,
            'ruts' => $usuarios->pluck('rut')->all(),
            'inmediata' => true,
        ]);

        $response->assertStatus(409);
    }

    public function test_reserva_que_excede_la_duracion_maxima_devuelve_422(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $sala = Sala::factory()->create();
        $usuarios = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '10:00',
            'hora_fin' => '12:30',
            'cantidad_personas' => 2,
            'ruts' => $usuarios->pluck('rut')->all(),
        ]);

        $response->assertStatus(422);
    }

    public function test_reserva_bajo_la_duracion_minima_devuelve_422(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $sala = Sala::factory()->create();
        $usuarios = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '10:00',
            'hora_fin' => '10:15',
            'cantidad_personas' => 2,
            'ruts' => $usuarios->pluck('rut')->all(),
        ]);

        $response->assertStatus(422);
    }

    public function test_inicio_no_alineado_a_la_media_hora_sin_inmediata_devuelve_422(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $sala = Sala::factory()->create();
        $usuarios = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '10:10',
            'hora_fin' => '11:10',
            'cantidad_personas' => 2,
            'ruts' => $usuarios->pluck('rut')->all(),
        ]);

        $response->assertStatus(422);
    }

    public function test_reserva_que_cruza_el_cierre_devuelve_422(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $sala = Sala::factory()->create();
        $usuarios = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '20:00',
            'hora_fin' => '22:00',
            'cantidad_personas' => 2,
            'ruts' => $usuarios->pluck('rut')->all(),
        ]);

        $response->assertStatus(422);
    }

    public function test_reserva_que_empieza_antes_de_la_apertura_devuelve_422(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $sala = Sala::factory()->create();
        $usuarios = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '07:30',
            'hora_fin' => '08:30',
            'cantidad_personas' => 2,
            'ruts' => $usuarios->pluck('rut')->all(),
        ]);

        $response->assertStatus(422);
    }

    public function test_cuota_diaria_excedida_devuelve_409(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $salaVieja = Sala::factory()->create();
        $salaNueva = Sala::factory()->create();
        $compartido = Usuario::factory()->create();
        $otro = Usuario::factory()->create();

        Reserva::factory()->conParticipantes([$compartido, $otro])->create([
            'sala_id' => $salaVieja->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '08:00',
            'hora_fin' => '12:00',
            'cantidad_personas' => 2,
        ]);

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $salaNueva->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '14:00',
            'hora_fin' => '14:30',
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $otro->rut],
        ]);

        $response->assertStatus(409);
    }

    public function test_reserva_no_show_no_consume_cuota_diaria(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        $salaVieja = Sala::factory()->create();
        $salaNueva = Sala::factory()->create();
        $compartido = Usuario::factory()->create();
        $otro = Usuario::factory()->create();

        Reserva::factory()->conParticipantes([$compartido, $otro])->create([
            'sala_id' => $salaVieja->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '08:00',
            'hora_fin' => '12:00',
            'cantidad_personas' => 2,
            'estado' => 'no_show',
        ]);

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $salaNueva->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '14:00',
            'hora_fin' => '16:00',
            'cantidad_personas' => 2,
            'ruts' => [$compartido->rut, $otro->rut],
        ]);

        $response->assertStatus(201);
    }

    public function test_inmediata_ignora_hora_inicio_del_cliente(): void
    {
        Sanctum::actingAs(Staff::factory()->create());
        Carbon::setTestNow('2026-07-10 15:42:07');

        $sala = Sala::factory()->create();
        $usuarios = Usuario::factory()->count(2)->create();

        $response = $this->postJson('/api/reservas', [
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '09:00',
            'hora_fin' => '16:42',
            'cantidad_personas' => 2,
            'ruts' => $usuarios->pluck('rut')->all(),
            'inmediata' => true,
        ]);

        $response->assertStatus(201)->assertJsonPath('hora_inicio', '15:42:00');
    }

    public function test_duracion_maxima_disponible_calcula_minutos_correctos(): void
    {
        $sala = Sala::factory()->create();
        $service = app(ReservaSalaService::class);

        $this->assertSame(120, $service->duracionMaximaDisponible($sala->id, '2026-07-10', '15:00'));

        Reserva::factory()->create([
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => '16:00',
            'hora_fin' => '17:00',
        ]);

        $this->assertSame(60, $service->duracionMaximaDisponible($sala->id, '2026-07-10', '15:00'));
        $this->assertSame(0, $service->duracionMaximaDisponible($sala->id, '2026-07-10', '16:30'));
    }
}
