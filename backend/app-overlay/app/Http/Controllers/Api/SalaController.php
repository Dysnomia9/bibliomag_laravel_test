<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reserva;
use App\Models\Sala;
use App\Models\Usuario;
use Illuminate\Http\Request;

class SalaController extends Controller
{
    public function index(Request $request)
    {
        $fecha = $request->query('fecha', now()->toDateString());

        $salas = Sala::orderBy('id')->get();
        $reservas = Reserva::where('fecha', $fecha)->get();

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
            'hora_inicio' => ['required', 'integer', 'min:0', 'max:23'],
            'hora_fin' => ['required', 'integer', 'gt:hora_inicio'],
            'rut_usuario' => ['required', 'string'],
            'nombre_usuario' => ['required', 'string'],
        ]);

        $existe = Reserva::where('sala_id', $data['sala_id'])
            ->where('fecha', $data['fecha'])
            ->where('hora_inicio', $data['hora_inicio'])
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'Ese bloque ya se encuentra reservado'], 409);
        }

        $usuario = Usuario::where('rut', $data['rut_usuario'])->first();

        $reserva = Reserva::create([
            'sala_id' => $data['sala_id'],
            'usuario_id' => $usuario?->id,
            'nombre_usuario' => $data['nombre_usuario'],
            'rut_usuario' => $data['rut_usuario'],
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
