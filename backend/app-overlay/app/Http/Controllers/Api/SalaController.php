<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Sala;
use App\Models\Usuario;
use App\Services\ReservaSalaService;
use Illuminate\Http\Request;

class SalaController extends Controller
{
    public function __construct(private ReservaSalaService $reservaSalaService)
    {
    }

    public function index(Request $request)
    {
        $fecha = $request->query('fecha', now()->toDateString());

        $salas = Sala::orderBy('id')->get();
        $reservas = Reserva::where('fecha', $fecha)->get();

        $ruts = $reservas->flatMap(fn ($r) => $r->ruts ?? [])->unique()->values();
        $usuariosPorRut = Usuario::whereIn('rut', $ruts)->get()->keyBy('rut');

        $reservas = $reservas->map(function ($r) use ($usuariosPorRut) {
            $r->personas = collect($r->ruts ?? [])->map(function ($rut) use ($usuariosPorRut) {
                $usuario = $usuariosPorRut->get($rut);

                return [
                    'rut' => $rut,
                    'nombre' => $usuario ? "{$usuario->nombre} {$usuario->apellido}" : null,
                ];
            })->values();

            return $r;
        });

        return response()->json([
            'fecha' => $fecha,
            'salas' => $salas,
            'reservas' => $reservas,
        ]);
    }

    public function storeReserva(Request $request)
    {
        $data = $request->validate([
            'sala_id' => ['required', 'exists:salas,id'],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'integer', 'min:8', 'max:20'],
            'hora_fin' => ['required', 'integer', 'gt:hora_inicio', 'max:21'],
            'cantidad_personas' => ['required', 'integer', 'min:2', 'max:5'],
            'ruts' => ['required', 'array'],
            'ruts.*' => ['required', 'string', 'distinct'],
        ], [
            'ruts.*.distinct' => 'No puedes ingresar el mismo RUT más de una vez en la misma reserva.',
        ]);

        if (count($data['ruts']) !== $data['cantidad_personas']) {
            return response()->json(['message' => 'Debe ingresar un RUT por cada persona indicada'], 422);
        }

        $existe = $this->reservaSalaService->existeSolapamiento(
            $data['sala_id'],
            $data['fecha'],
            $data['hora_inicio'],
            $data['hora_fin'],
        );

        if ($existe) {
            return response()->json(['message' => 'Ese bloque ya se encuentra reservado'], 409);
        }

        $rutConflicto = $this->reservaSalaService->participanteConReservaSolapada(
            $data['ruts'],
            $data['fecha'],
            $data['hora_inicio'],
            $data['hora_fin'],
        );

        if ($rutConflicto) {
            return response()->json(['message' => "El RUT {$rutConflicto} ya tiene otra sala reservada en ese horario"], 409);
        }

        $usuario = Usuario::where('rut', $data['ruts'][0])->first();

        $reserva = Reserva::create([
            'sala_id' => $data['sala_id'],
            'usuario_id' => $usuario?->id,
            'rut_usuario' => $data['ruts'][0],
            'cantidad_personas' => $data['cantidad_personas'],
            'ruts' => $data['ruts'],
            'fecha' => $data['fecha'],
            'hora_inicio' => $data['hora_inicio'],
            'hora_fin' => $data['hora_fin'],
            'estado' => 'activa',
        ]);

        return response()->json($reserva, 201);
    }

    public function destroyReserva(Reserva $reserva)
    {
        $reserva->delete();

        return response()->json(null, 204);
    }
}
