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

        if ($tipoItem = $request->query('tipo_item')) {
            $query->where('tipo_item', $tipoItem);
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
            'tipo_item' => ['sometimes', 'in:libro,audifonos,notebook'],
            'dias_prestamo' => ['sometimes', 'integer', 'in:7,14,30'],
        ]);

        $tipoItem = $data['tipo_item'] ?? 'libro';
        // Los equipos tecnológicos (audífonos, notebooks) se prestan por código de
        // inventario y se devuelven al término de la estadía en la biblioteca, sin
        // una fecha de vencimiento fija como los libros.
        $esEquipo = $tipoItem !== 'libro';

        $prestamo = Prestamo::create([
            'usuario_id' => $data['usuario_id'],
            'libro_titulo' => $data['libro_titulo'],
            'tipo_item' => $tipoItem,
            'fecha_prestamo' => now(),
            'fecha_devolucion' => $esEquipo ? null : now()->addDays($data['dias_prestamo'] ?? 14),
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
