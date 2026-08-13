<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Libro;
use App\Models\ReservaLibro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservaLibroController extends Controller
{
    public function index(Request $request)
    {
        $query = ReservaLibro::with('libro');

        if ($usuarioId = $request->query('usuario_id')) {
            $query->where('usuario_id', $usuarioId);
        }

        return response()->json(
            $query->latest('fecha_reserva')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'usuario_id' => ['required', 'exists:usuarios,id'],
            'codigo_barras' => ['required', 'string'],
            'fecha_reserva' => ['required', 'date'],
            'fecha_retiro' => ['required', 'date', 'after_or_equal:fecha_reserva'],
        ]);

        // Todo el ciclo lectura→decisión→escritura va en una transacción con
        // lockForUpdate(): mismo riesgo de doble asignación del mismo ejemplar
        // único que en PrestamoController::store() si no se protege.
        [$response, $status] = DB::transaction(function () use ($data) {
            $libro = Libro::where('codigo_barras', $data['codigo_barras'])->lockForUpdate()->first();

            if (! $libro) {
                return [['message' => 'Código de barras no encontrado en el sistema'], 404];
            }

            // Una reserva existe justamente para bloquear el libro mientras otra persona lo
            // tiene o lo está esperando: no se puede volver a reservar/prestar un libro que ya
            // está ocupado por otra reserva pendiente.
            if (! $libro->disponible) {
                return [['message' => 'Este libro ya está reservado/prestado por otra persona'], 409];
            }

            if ($libro->estado_proceso !== 'en_estante') {
                return [['message' => "Este libro no está disponible para préstamo (estado: {$libro->estado_proceso})"], 409];
            }

            $reserva = ReservaLibro::create([
                'usuario_id' => $data['usuario_id'],
                'libro_id' => $libro->id,
                'fecha_reserva' => $data['fecha_reserva'],
                'fecha_retiro' => $data['fecha_retiro'],
                'estado' => 'pendiente',
            ]);

            $libro->update(['disponible' => false]);

            $reserva->load('libro');

            return [$reserva, 201];
        });

        return response()->json($response, $status);
    }

    public function cancelar(ReservaLibro $reservaLibro)
    {
        DB::transaction(function () use ($reservaLibro) {
            $reservaLibro->update(['estado' => 'cancelado']);
            Libro::whereKey($reservaLibro->libro_id)->lockForUpdate()->first()?->update(['disponible' => true]);
        });

        return response()->json($reservaLibro);
    }
}
