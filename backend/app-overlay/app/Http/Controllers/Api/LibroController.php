<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Libro;

class LibroController extends Controller
{
    public function buscarPorCodigo(string $codigo)
    {
        $libro = Libro::where('codigo_barras', $codigo)->first();

        if (! $libro) {
            return response()->json(['message' => 'Código de barras no encontrado en el sistema'], 404);
        }

        return response()->json($libro);
    }
}
