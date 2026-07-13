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

    public function store(Request $request)
    {
        $data = $request->validate($this->reglasCatalogacion());

        $libro = Libro::create($data);

        return response()->json($libro, 201);
    }

    public function update(Request $request, Libro $libro)
    {
        $data = $request->validate($this->reglasCatalogacion($libro->id));

        $libro->update($data);

        return response()->json($libro);
    }

    public function cambiarEstado(Request $request, Libro $libro)
    {
        $data = $request->validate([
            'estado_proceso' => ['required', 'in:inventario,procesos_tecnicos,por_colocar,en_estante,estanteria_auxiliar,de_baja'],
        ]);

        // No se puede dar de baja un libro que en este momento está en manos de otra
        // persona (prestado o reservado) — primero debe devolverse/cancelarse.
        if ($data['estado_proceso'] === 'de_baja' && ! $libro->disponible) {
            return response()->json(['message' => 'No se puede dar de baja un libro actualmente prestado o reservado'], 409);
        }

        $data['fecha_inventario'] = $data['estado_proceso'] === 'inventario' ? now() : $libro->fecha_inventario;

        $libro->update($data);

        return response()->json($libro);
    }

    private function reglasCatalogacion(?int $ignorarLibroId = null): array
    {
        return [
            'codigo_barras' => ['required', 'string', 'max:255', 'unique:libros,codigo_barras,' . ($ignorarLibroId ?? 'NULL') . ',id'],
            'titulo' => ['required', 'string', 'max:255'],
            'autor' => ['nullable', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'clasificacion' => ['nullable', 'string', 'max:255'],
            'coleccion' => ['nullable', 'string', 'max:255'],
            'editorial' => ['nullable', 'string', 'max:255'],
            'anio_publicacion' => ['nullable', 'integer', 'min:1000', 'max:9999'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'tipo_material' => ['nullable', 'in:libro,revista,tesis,dvd,otro'],
            'volumen' => ['nullable', 'string', 'max:100'],
            'nota_interna' => ['nullable', 'string'],
            'nota_publica' => ['nullable', 'string'],
            'precio' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
