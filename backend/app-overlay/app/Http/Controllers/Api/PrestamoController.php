<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Libro;
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
        // Los libros del catálogo se prestan escaneando su código de barras (y con fecha de
        // préstamo/devolución acordadas en el calendario); los equipos tecnológicos
        // (audífonos, notebooks) no están en el catálogo y se identifican por código de
        // inventario en texto libre, sin fecha de vencimiento fija.
        $request->merge(['tipo_item' => $request->input('tipo_item', 'libro')]);

        $data = $request->validate([
            'usuario_id' => ['required', 'exists:usuarios,id'],
            'tipo_item' => ['required', 'in:libro,audifonos,notebook'],
            'codigo_barras' => ['required_if:tipo_item,libro', 'nullable', 'string'],
            'libro_titulo' => ['required_if:tipo_item,audifonos,notebook', 'nullable', 'string', 'max:255'],
            'fecha_prestamo' => ['required_if:tipo_item,libro', 'nullable', 'date'],
            'fecha_devolucion' => ['required_if:tipo_item,libro', 'nullable', 'date', 'after_or_equal:fecha_prestamo'],
            'prestado_por' => ['nullable', 'string', 'max:255'],
        ]);

        $esLibro = $data['tipo_item'] === 'libro';
        $libro = null;

        if ($esLibro) {
            $libro = Libro::where('codigo_barras', $data['codigo_barras'])->first();

            if (! $libro) {
                return response()->json(['message' => 'Código de barras no encontrado en el sistema'], 404);
            }

            if (! $libro->disponible) {
                return response()->json(['message' => 'Este libro ya está reservado/prestado por otra persona'], 409);
            }
        }

        $prestamo = Prestamo::create([
            'usuario_id' => $data['usuario_id'],
            'libro_titulo' => $esLibro ? $libro->titulo : $data['libro_titulo'],
            'tipo_item' => $data['tipo_item'],
            'codigo_barras' => $esLibro ? $libro->codigo_barras : null,
            'fecha_prestamo' => $esLibro ? $data['fecha_prestamo'] : now(),
            'fecha_devolucion' => $esLibro ? $data['fecha_devolucion'] : null,
            'estado' => 'activo',
            'prestado_por' => $data['prestado_por'] ?? null,
        ]);

        $libro?->update(['disponible' => false]);

        $prestamo->load('usuario:id,nombre,apellido,rut');

        return response()->json($prestamo, 201);
    }

    public function devolver(Request $request, Prestamo $prestamo)
    {
        $data = $request->validate([
            'devuelto_por' => ['nullable', 'string', 'max:255'],
        ]);

        $prestamo->update([
            'fecha_devolucion_real' => now(),
            'estado' => 'devuelto',
            'devuelto_por' => $data['devuelto_por'] ?? null,
        ]);

        if ($prestamo->codigo_barras) {
            Libro::where('codigo_barras', $prestamo->codigo_barras)->update(['disponible' => true]);
        }

        return response()->json($prestamo);
    }
}
