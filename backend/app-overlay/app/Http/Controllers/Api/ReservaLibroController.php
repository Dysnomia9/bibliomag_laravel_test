<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Libro;
use App\Models\ReservaLibro;
use Illuminate\Http\Request;

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

        $libro = Libro::where('codigo_barras', $data['codigo_barras'])->first();

        if (! $libro) {
            return response()->json(['message' => 'Código de barras no encontrado en el sistema'], 404);
        }

        // Una reserva existe justamente para bloquear el libro mientras otra persona lo
        // tiene o lo está esperando: no se puede volver a reservar/prestar un libro que ya
        // está ocupado por otra reserva pendiente.
        if (! $libro->disponible) {
            return response()->json(['message' => 'Este libro ya está reservado/prestado por otra persona'], 409);
        }

        if ($libro->estado_proceso !== 'en_estante') {
            return response()->json(['message' => "Este libro no está disponible para préstamo (estado: {$libro->estado_proceso})"], 409);
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

        return response()->json($reserva, 201);
    }

    public function cancelar(ReservaLibro $reservaLibro)
    {
        $reservaLibro->update(['estado' => 'cancelado']);
        $reservaLibro->libro->update(['disponible' => true]);

        return response()->json($reservaLibro);
    }
}
