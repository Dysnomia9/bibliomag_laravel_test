<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prestamo;
use Illuminate\Http\Request;

class PrestamoController extends Controller
{
    public function index(Request $request)
    {
        $query = Prestamo::with('usuario:id,nombre,apellido,rut');

        if ($usuarioId = $request->query('usuario_id')) {
            $query->where('usuario_id', $usuarioId);
        }

        if ($estado = $request->query('estado')) {
            $query->where('estado', $estado);
        }

        return response()->json(
            $query->latest('fecha_prestamo')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'usuario_id' => ['required', 'exists:usuarios,id'],
            'libro_titulo' => ['required', 'string', 'max:255'],
            'dias_prestamo' => ['sometimes', 'integer', 'in:7,14,30'],
        ]);

        $prestamo = Prestamo::create([
            'usuario_id' => $data['usuario_id'],
            'libro_titulo' => $data['libro_titulo'],
            'fecha_prestamo' => now(),
            'fecha_devolucion' => now()->addDays($data['dias_prestamo'] ?? 14),
            'estado' => 'activo',
        ]);

        $prestamo->load('usuario:id,nombre,apellido,rut');

        return response()->json($prestamo, 201);
    }

    public function devolver(Prestamo $prestamo)
    {
        $prestamo->update([
            'fecha_devolucion_real' => now(),
            'estado' => 'devuelto',
        ]);

        return response()->json($prestamo);
    }
}
