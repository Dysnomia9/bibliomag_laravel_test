<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\Usuario;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $query = Usuario::query();

        if ($busqueda = $request->query('q')) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombre', 'ilike', "%{$busqueda}%")
                    ->orWhere('apellido', 'ilike', "%{$busqueda}%")
                    ->orWhere('rut', 'ilike', "%{$busqueda}%")
                    ->orWhere('carrera', 'ilike', "%{$busqueda}%");
            });
        }

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        if ($request->has('activo')) {
            $query->where('activo', filter_var($request->query('activo'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(
            $query->orderBy('nombre')->orderBy('apellido')->get()
        );
    }

    public function store(StoreUsuarioRequest $request)
    {
        $usuario = Usuario::create($request->validated());

        return response()->json($usuario, 201);
    }

    public function update(UpdateUsuarioRequest $request, Usuario $usuario)
    {
        $usuario->update($request->validated());

        return response()->json($usuario);
    }

    public function toggleActivo(Usuario $usuario)
    {
        $usuario->update(['activo' => ! $usuario->activo]);

        return response()->json($usuario);
    }
}
