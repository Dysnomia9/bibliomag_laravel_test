<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipo;
use Illuminate\Http\Request;

class EquipoController extends Controller
{
    public function index(Request $request)
    {
        $query = Equipo::query();

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        if ($request->has('disponible')) {
            $query->where('disponible', filter_var($request->query('disponible'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('activo')) {
            $query->where('activo', filter_var($request->query('activo'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(
            $query->orderBy('codigo_inventario')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'codigo_inventario' => ['required', 'string', 'max:255', 'unique:equipos,codigo_inventario'],
            'tipo' => ['required', 'in:audifonos,notebook'],
        ]);

        $equipo = Equipo::create($data + ['disponible' => true, 'activo' => true]);

        return response()->json($equipo, 201);
    }

    public function cambiarActivo(Request $request, Equipo $equipo)
    {
        $data = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        // No se puede dar de baja un equipo actualmente prestado (mismo criterio que
        // LibroController::cambiarEstado con 'de_baja') — primero debe devolverse.
        if ($data['activo'] === false && ! $equipo->disponible) {
            return response()->json(['message' => 'No se puede dar de baja un equipo actualmente prestado'], 409);
        }

        $equipo->update(['activo' => $data['activo']]);

        return response()->json($equipo);
    }
}
