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
        $reservas = Reserva::with('participantes:id,nombre,apellido,rut')
            ->where('fecha', $fecha)
            ->where('estado', '!=', 'no_show')
            ->get();

        // Expiración perezosa: si al cargar la vista alguna reserva ya venció su plazo de
        // 15 minutos sin confirmar, se libera de inmediato en vez de esperar a que otra
        // persona intente reservar ese mismo bloque.
        $reservas = $reservas->reject(fn ($r) => $this->reservaSalaService->liberarSiVencida($r))->values();

        $reservas = $reservas->map(function ($r) {
            $r->personas = $r->participantes->map(fn ($u) => [
                'rut' => $u->rut,
                'nombre' => "{$u->nombre} {$u->apellido}",
            ])->values();
            $r->plazo_confirmacion = $r->plazoConfirmacion();
            $r->vencida_sin_confirmar = $r->estaVencidaSinConfirmar();

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
            // Los RUT deben pertenecer a usuarios ya registrados: no se admiten
            // visitantes externos en la reserva de logias (mismo criterio que el
            // portal de autoservicio en PortalController::reservarSala).
            'ruts.*' => ['required', 'string', 'distinct', 'exists:usuarios,rut'],
        ], [
            'ruts.*.distinct' => 'No puedes ingresar el mismo RUT más de una vez en la misma reserva.',
            'ruts.*.exists' => 'Uno de los RUT ingresados no corresponde a un usuario registrado en el sistema.',
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

        $excedeLimite = $this->reservaSalaService->participanteExcedeLimiteDeBloques(
            $data['ruts'],
            $data['sala_id'],
            $data['fecha'],
            $data['hora_inicio'],
            $data['hora_fin'],
        );

        if ($excedeLimite) {
            return response()->json(['message' => $this->reservaSalaService->mensajeLimiteBloques($excedeLimite)], 409);
        }

        $usuarios = Usuario::whereIn('rut', $data['ruts'])->get()->keyBy('rut');

        $reserva = Reserva::create([
            'sala_id' => $data['sala_id'],
            'usuario_id' => $usuarios[$data['ruts'][0]]->id,
            'rut_usuario' => $data['ruts'][0],
            'cantidad_personas' => $data['cantidad_personas'],
            'fecha' => $data['fecha'],
            'hora_inicio' => $data['hora_inicio'],
            'hora_fin' => $data['hora_fin'],
            'estado' => 'activa',
        ]);

        $reserva->participantes()->attach($usuarios->pluck('id'));

        return response()->json($reserva, 201);
    }

    public function destroyReserva(Reserva $reserva)
    {
        $reserva->delete();

        return response()->json(null, 204);
    }

    /**
     * Confirmación manual de devolución de llave (sin escaneo de código de barras) —
     * a diferencia de destroyReserva(), no borra la reserva: deja registrado quién y
     * cuándo se devolvió, igual que hace escanearLogia() por la vía del código de barras.
     */
    public function devolverReserva(Request $request, Reserva $reserva)
    {
        $data = $request->validate([
            'registrado_por' => ['required', 'string', 'max:255'],
        ]);

        try {
            $reserva = $this->reservaSalaService->registrarDevolucion($reserva, $data['registrado_por']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($reserva);
    }

    /**
     * Confirmación manual de llegada (sin escaneo de código de barras) — sirve para
     * cualquier sala, incluidas las que no tienen codigo_barras (Seminarios, Postgrado,
     * AGACI). Es el "menú de confirmación": el staff ve el listado de reservas del día
     * y marca aquí quién sí se presentó dentro del plazo de 15 minutos.
     */
    public function confirmarLlegada(Request $request, Reserva $reserva)
    {
        $data = $request->validate([
            'registrado_por' => ['required', 'string', 'max:255'],
        ]);

        try {
            $reserva = $this->reservaSalaService->registrarLlegada($reserva, $data['registrado_por']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($reserva);
    }

    /** Libera de inmediato una reserva vencida sin confirmar, sin esperar a un nuevo intento de reserva sobre el mismo bloque. */
    public function liberarReserva(Reserva $reserva)
    {
        try {
            $reserva = $this->reservaSalaService->liberarPorNoPresentacion($reserva);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($reserva);
    }

    public function scanLogia(Request $request)
    {
        $data = $request->validate([
            'codigo_barras' => ['required', 'string'],
            'registrado_por' => ['required', 'string', 'max:255'],
        ]);

        try {
            $reserva = $this->reservaSalaService->escanearLogia($data['codigo_barras'], $data['registrado_por']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($reserva->load('sala'));
    }
}
