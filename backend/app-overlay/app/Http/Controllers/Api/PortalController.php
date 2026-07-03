<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrada;
use App\Models\Libro;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PortalController extends Controller
{
    private const CAPACIDAD_SALA = 220;

    public function estado(Request $request)
    {
        $hoy = Carbon::today();

        return response()->json([
            'usuario' => $request->user(),
            'personasEnSala' => Entrada::whereDate('fecha_hora_entrada', $hoy)
                ->whereNull('fecha_hora_salida')
                ->count(),
            'capacidad' => self::CAPACIDAD_SALA,
        ]);
    }

    public function registrarEntrada(Request $request)
    {
        $data = $request->validate([
            'rut' => ['sometimes', 'string'],
            'via' => ['required', 'in:manual,qr'],
        ]);

        $usuario = $request->user();

        if ($data['via'] === 'manual' && ($data['rut'] ?? null) !== $usuario->rut) {
            return response()->json(['message' => 'El RUT ingresado no coincide con tu cuenta'], 422);
        }

        $entrada = Entrada::create([
            'usuario_id' => $usuario->id,
            'via' => $data['via'],
        ]);

        return response()->json([
            'entrada' => $entrada,
            'usuario' => $usuario,
        ], 201);
    }

    public function catalogo(Request $request)
    {
        $query = Libro::query();

        if ($busqueda = $request->query('q')) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('titulo', 'ilike', "%{$busqueda}%")
                    ->orWhere('autor', 'ilike', "%{$busqueda}%")
                    ->orWhere('categoria', 'ilike', "%{$busqueda}%");
            });
        }

        return response()->json(
            $query->orderBy('titulo')->get()
        );
    }
}
