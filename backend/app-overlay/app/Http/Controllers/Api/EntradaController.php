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

        return response()->json([
            'fecha' => $fecha,
            'entradas' => $entradas,
            // Horizon (el sistema legado) no distingue "quién sigue adentro": cada
            // ingreso se cierra en el mismo instante en que se registra (ver store()).
            // "Personas en sala" es, en la práctica, el total de ingresos del día — no
            // un conteo en tiempo real de quién no ha salido.
            'personasEnSala' => $entradas->count(),
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

        // Horizon gestiona toda la asistencia (usuarios, docentes, funcionarios) con el
        // código de barras del puesto de trabajo: se estampa siempre automáticamente, nunca
        // se tipea a mano. La salida se marca en el mismo instante que la entrada — Horizon
        // no registra un evento de salida por separado, así que no tiene sentido modelar un
        // estado "activo" que dependa de fecha_hora_salida (antes quedaba una entrada
        // "abierta" indefinidamente si nadie la cerraba a mano, incluso días después). Se usa
        // el mismo timestamp para ambos campos (no dos now() distintos) para que salida no
        // quede unos milisegundos después de entrada.
        $ahora = now();
        $entrada = Entrada::create([
            'usuario_id' => $usuario->id,
            'via' => $data['via'] ?? 'manual',
            'codigo_barras' => config('horizon_barcodes.puesto_generico'),
            'fecha_hora_entrada' => $ahora,
            'fecha_hora_salida' => $ahora,
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

        // Visitantes externos no están en la base de datos institucional: se
        // registran directamente con el RUT (y nombre opcional) que declaran,
        // sin validar contra la tabla de usuarios.
        $ahora = now();
        $entrada = Entrada::create([
            'rut_externo' => $data['rut'],
            'nombre_externo' => $data['nombre'] ?? null,
            'via' => 'manual',
            'codigo_barras' => config('horizon_barcodes.puesto_generico'),
            'fecha_hora_entrada' => $ahora,
            'fecha_hora_salida' => $ahora,
        ]);

        return response()->json($entrada, 201);
    }

    public function storeConvenio(Request $request)
    {
        $data = $request->validate([
            'rut' => ['required', 'string'],
            'nombre' => ['nullable', 'string', 'max:255'],
        ]);

        // Personas de convenio institucional: mismo flujo que un externo (no están en la
        // base de datos institucional), pero se marcan aparte para reportería.
        $ahora = now();
        $entrada = Entrada::create([
            'rut_externo' => $data['rut'],
            'nombre_externo' => $data['nombre'] ?? null,
            'es_convenio' => true,
            'via' => 'manual',
            'codigo_barras' => config('horizon_barcodes.puesto_generico'),
            'fecha_hora_entrada' => $ahora,
            'fecha_hora_salida' => $ahora,
        ]);

        return response()->json($entrada, 201);
    }
}
