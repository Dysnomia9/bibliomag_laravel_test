<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrada;
use App\Models\Usuario;
use Illuminate\Http\Request;

class EntradaController extends Controller
{
    public function index(Request $request)
    {
        $fecha = $request->query('fecha', now()->toDateString());

        $entradas = Entrada::with('usuario:id,nombre,apellido,rut')
            ->whereDate('fecha_hora_entrada', $fecha)
            ->latest('fecha_hora_entrada')
            ->get();

        $personasEnSala = Entrada::whereDate('fecha_hora_entrada', $fecha)
            ->whereNull('fecha_hora_salida')
            ->count();

        return response()->json([
            'fecha' => $fecha,
            'entradas' => $entradas,
            'personasEnSala' => $personasEnSala,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'rut' => ['required', 'string'],
            'via' => ['sometimes', 'in:manual,qr'],
        ]);

        $usuario = Usuario::where('rut', $data['rut'])->first();

        if (! $usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        if (! $usuario->activo) {
            return response()->json(['message' => 'El usuario se encuentra inactivo'], 403);
        }

        $entrada = Entrada::create([
            'usuario_id' => $usuario->id,
            'via' => $data['via'] ?? 'manual',
        ]);

        $entrada->load('usuario:id,nombre,apellido,rut');

        return response()->json($entrada, 201);
    }
}
