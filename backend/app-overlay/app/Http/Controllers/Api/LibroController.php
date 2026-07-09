<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Libro;
use Illuminate\Http\Request;

class LibroController extends Controller
{
    public function index(Request $request)
    {
        $query = Libro::query();

        if ($busqueda = $request->query('q')) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('titulo', 'ilike', "%{$busqueda}%")
                    ->orWhere('autor', 'ilike', "%{$busqueda}%")
                    ->orWhere('codigo_barras', 'ilike', "%{$busqueda}%");
            });
        }

        return response()->json(
            $query->orderBy('titulo')->get()
        );
    }

    public function buscarPorCodigo(string $codigo)
    {
        $libro = Libro::where('codigo_barras', $codigo)->first();

        if (! $libro) {
            return response()->json(['message' => 'Código de barras no encontrado en el sistema'], 404);
        }

        return response()->json($libro);
    }
}
