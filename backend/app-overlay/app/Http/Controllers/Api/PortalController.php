<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CodigoAcceso;
use App\Models\Entrada;
use App\Models\Libro;
use App\Models\Reserva;
use App\Models\Sala;
use App\Services\ReservaSalaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PortalController extends Controller
{
    private const CAPACIDAD_SALA = 220;

    public function __construct(private ReservaSalaService $reservaSalaService)
    {
    }

    public function estado(Request $request)
    {
        $hoy = Carbon::today();

        return response()->json([
            'usuario' => $request->user(),
            'personasEnSala' => Entrada::whereDate('fecha_hora_entrada', $hoy)
                ->whereNull('fecha_hora_salida')
                ->count(),
            'capacidad' => self::CAPACIDAD_SALA,
            'entradaActiva' => Entrada::where('usuario_id', $request->user()->id)
                ->whereNull('fecha_hora_salida')
                ->exists(),
        ]);
    }

    public function registrarEntrada(Request $request)
    {
        $data = $request->validate([
            'rut' => ['sometimes', 'string'],
            'codigo' => ['required_if:via,qr', 'string'],
            'via' => ['required', 'in:manual,qr'],
        ]);

        $usuario = $request->user();

        if ($data['via'] === 'manual' && ($data['rut'] ?? null) !== $usuario->rut) {
            return response()->json(['message' => 'El RUT ingresado no coincide con tu cuenta'], 422);
        }

        if ($data['via'] === 'qr' && $data['codigo'] !== CodigoAcceso::vigente()->codigo) {
            return response()->json(['message' => 'El código QR no es válido. Pide al personal que lo actualice.'], 422);
        }

        $tieneEntradaActiva = Entrada::where('usuario_id', $usuario->id)
            ->whereNull('fecha_hora_salida')
            ->exists();

        if ($tieneEntradaActiva) {
            return response()->json(['message' => 'Ya tienes una entrada activa registrada'], 409);
        }

        $entrada = Entrada::create([
            'usuario_id' => $usuario->id,
            'via' => $data['via'],
        ]);

        return response()->json([
            'entrada' => $entrada,
            'usuario' => $usuario,
        ], 201);
    }

    public function registrarSalida(Request $request)
    {
        $entrada = Entrada::where('usuario_id', $request->user()->id)
            ->whereNull('fecha_hora_salida')
            ->latest('fecha_hora_entrada')
            ->first();

        if (! $entrada) {
            return response()->json(['message' => 'No tienes una entrada activa registrada'], 404);
        }

        $entrada->update(['fecha_hora_salida' => now()]);

        return response()->json($entrada);
    }

    public function catalogo(Request $request)
    {
        $query = Libro::query();

        if ($busqueda = $request->query('q')) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('titulo', 'ilike', "%{$busqueda}%")
                    ->orWhere('autor', 'ilike', "%{$busqueda}%")
                    ->orWhere('categoria', 'ilike', "%{$busqueda}%");
            });
        }

        return response()->json(
            $query->orderBy('titulo')->get()
        );
    }

    public function salas(Request $request)
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

    public function reservarSala(Request $request)
    {
        $data = $request->validate([
            'sala_id' => ['required', 'exists:salas,id'],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['required', 'integer', 'min:8', 'max:20'],
            'hora_fin' => ['required', 'integer', 'gt:hora_inicio', 'max:21'],
            'cantidad_personas' => ['required', 'integer', 'min:2', 'max:5'],
            'ruts' => ['required', 'array'],
            // A diferencia del registro de entrada externo, aquí los RUT deben
            // pertenecer a usuarios ya registrados: no se admiten visitantes externos.
            'ruts.*' => ['required', 'string', 'distinct', 'exists:usuarios,rut'],
        ], [
            'ruts.*.exists' => 'Uno de los RUT ingresados no corresponde a un usuario registrado en el sistema.',
            'ruts.*.distinct' => 'No puedes ingresar el mismo RUT más de una vez en la misma reserva.',
        ]);

        if (count($data['ruts']) !== $data['cantidad_personas']) {
            return response()->json(['message' => 'Debe ingresar un RUT por cada persona indicada'], 422);
        }

        $usuario = $request->user();

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
            $mensaje = $rutConflicto === $usuario->rut
                ? 'Ya tienes otra sala reservada en ese horario'
                : "El RUT {$rutConflicto} ya tiene otra sala reservada en ese horario";

            return response()->json(['message' => $mensaje], 409);
        }

        $reserva = Reserva::create([
            'sala_id' => $data['sala_id'],
            'usuario_id' => $usuario->id,
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

    public function cancelarReservaSala(Request $request, Reserva $reserva)
    {
        if ($reserva->usuario_id !== $request->user()->id) {
            return response()->json(['message' => 'Solo puedes cancelar tus propias reservas'], 403);
        }

        $reserva->delete();

        return response()->json(null, 204);
    }
}
