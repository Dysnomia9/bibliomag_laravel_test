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

        Reserva::factory()->create([
            'sala_id' => $sala->id,
            'fecha' => '2026-07-10',
            'hora_inicio' => 10,
            'hora_fin' => 12,
            'cantidad_personas' => 2,
            'ruts' => $ocupantes->pluck('rut')->all(),
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
}
