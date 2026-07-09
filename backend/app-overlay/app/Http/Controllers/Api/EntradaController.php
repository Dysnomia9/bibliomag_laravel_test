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

        $entradas = Entrada::with('usuario:id,nombre,apellido,rut,tipo')
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

        $tieneEntradaActiva = Entrada::where('usuario_id', $usuario->id)
            ->whereNull('fecha_hora_salida')
            ->exists();

        if ($tieneEntradaActiva) {
            return response()->json(['message' => 'El usuario ya tiene una entrada activa registrada'], 409);
        }

        // Horizon gestiona toda la asistencia (usuarios, docentes, funcionarios) con el
        // código de barras del puesto de trabajo: se estampa siempre automáticamente, nunca
        // se tipea a mano.
        $entrada = Entrada::create([
            'usuario_id' => $usuario->id,
            'via' => $data['via'] ?? 'manual',
            'codigo_barras' => config('horizon_barcodes.puesto_generico'),
        ]);

        $entrada->load('usuario:id,nombre,apellido,rut,tipo');

        return response()->json($entrada, 201);
    }

    public function storeExterno(Request $request)
    {
        $data = $request->validate([
            'rut' => ['required', 'string'],
            'nombre' => ['nullable', 'string', 'max:255'],
        ]);

        $tieneEntradaActiva = Entrada::where('rut_externo', $data['rut'])
            ->whereNull('fecha_hora_salida')
            ->exists();

        if ($tieneEntradaActiva) {
            return response()->json(['message' => 'Ese visitante ya tiene una entrada activa registrada'], 409);
        }

        // Visitantes externos no están en la base de datos institucional: se
        // registran directamente con el RUT (y nombre opcional) que declaran,
        // sin validar contra la tabla de usuarios.
        $entrada = Entrada::create([
            'rut_externo' => $data['rut'],
            'nombre_externo' => $data['nombre'] ?? null,
            'via' => 'manual',
            'codigo_barras' => config('horizon_barcodes.puesto_generico'),
        ]);

        return response()->json($entrada, 201);
    }

    public function storeConvenio(Request $request)
    {
        $data = $request->validate([
            'rut' => ['required', 'string'],
            'nombre' => ['nullable', 'string', 'max:255'],
        ]);

        $tieneEntradaActiva = Entrada::where('rut_externo', $data['rut'])
            ->whereNull('fecha_hora_salida')
            ->exists();

        if ($tieneEntradaActiva) {
            return response()->json(['message' => 'Esa persona ya tiene una entrada activa registrada'], 409);
        }

        // Personas de convenio institucional: mismo flujo que un externo (no están en la
        // base de datos institucional), pero se marcan aparte para reportería.
        $entrada = Entrada::create([
            'rut_externo' => $data['rut'],
            'nombre_externo' => $data['nombre'] ?? null,
            'es_convenio' => true,
            'via' => 'manual',
            'codigo_barras' => config('horizon_barcodes.puesto_generico'),
        ]);

        return response()->json($entrada, 201);
    }

    public function marcarSalida(Entrada $entrada)
    {
        if ($entrada->fecha_hora_salida !== null) {
            return response()->json(['message' => 'Esta entrada ya tiene una salida registrada'], 409);
        }

        $entrada->update(['fecha_hora_salida' => now()]);

        $entrada->load('usuario:id,nombre,apellido,rut,tipo');

        return response()->json($entrada);
    }
}
