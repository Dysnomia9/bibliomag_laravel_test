<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipo;
use App\Models\Libro;
use App\Models\Prestamo;
use App\Services\MultaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestamoController extends Controller
{
    public function __construct(private MultaService $multaService)
    {
    }

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
        ]);

        $staff = $request->user();
        $esLibro = $data['tipo_item'] === 'libro';

        // Todo el ciclo lectura→decisión→escritura va en una transacción con
        // lockForUpdate(): sin esto, dos requests concurrentes sobre el mismo
        // codigo_barras/codigo_inventario pueden pasar ambas el chequeo
        // "disponible" antes de que la primera escriba, resultando en doble
        // préstamo del mismo ejemplar único (no hay modelo Ejemplar, ver CLAUDE.md).
        [$response, $status] = DB::transaction(function () use ($data, $esLibro, $staff) {
            $libro = null;
            $equipo = null;

            if ($esLibro) {
                $libro = Libro::where('codigo_barras', $data['codigo_barras'])->lockForUpdate()->first();

                if (! $libro) {
                    return [['message' => 'Código de barras no encontrado en el sistema'], 404];
                }

                if (! $libro->disponible) {
                    return [['message' => 'Este libro ya está reservado/prestado por otra persona'], 409];
                }

                if ($libro->estado_proceso !== 'en_estante') {
                    return [['message' => "Este libro no está disponible para préstamo (estado: {$libro->estado_proceso})"], 409];
                }
            } else {
                $equipo = Equipo::where('codigo_inventario', $data['libro_titulo'])->where('tipo', $data['tipo_item'])->lockForUpdate()->first();

                if (! $equipo) {
                    return [['message' => 'Código de inventario no encontrado en el sistema'], 404];
                }

                if (! $equipo->activo) {
                    return [['message' => 'Este equipo fue dado de baja'], 409];
                }

                if (! $equipo->disponible) {
                    return [['message' => 'Este equipo ya está prestado a otra persona'], 409];
                }
            }

            $prestamo = Prestamo::create([
                'usuario_id' => $data['usuario_id'],
                'libro_id' => $libro?->id,
                'equipo_id' => $equipo?->id,
                'libro_titulo' => $esLibro ? $libro->titulo : $equipo->codigo_inventario,
                'tipo_item' => $data['tipo_item'],
                'codigo_barras' => $esLibro ? $libro->codigo_barras : null,
                'fecha_prestamo' => $esLibro ? $data['fecha_prestamo'] : now(),
                'fecha_devolucion' => $esLibro ? $data['fecha_devolucion'] : null,
                'estado' => 'activo',
                'prestado_por' => $staff->nombre,
                'prestado_por_staff_id' => $staff->id,
            ]);

            $libro?->update(['disponible' => false]);
            $equipo?->update(['disponible' => false]);

            $prestamo->load('usuario:id,nombre,apellido,rut');

            return [$prestamo, 201];
        });

        return response()->json($response, $status);
    }

    public function devolver(Request $request, Prestamo $prestamo)
    {
        $staff = $request->user();
        $ahora = now();

        DB::transaction(function () use ($prestamo, $staff, $ahora) {
            $multa = $this->multaService->calcular($prestamo, $ahora);

            $prestamo->update([
                'fecha_devolucion_real' => $ahora,
                'estado' => 'devuelto',
                'devuelto_por' => $staff->nombre,
                'devuelto_por_staff_id' => $staff->id,
                'multa_monto' => $multa,
                'multa_estado' => $multa ? 'pendiente' : null,
            ]);

            if ($prestamo->libro_id) {
                Libro::whereKey($prestamo->libro_id)->lockForUpdate()->first()?->update(['disponible' => true]);
            }

            if ($prestamo->equipo_id) {
                Equipo::whereKey($prestamo->equipo_id)->lockForUpdate()->first()?->update(['disponible' => true]);
            }
        });

        return response()->json($prestamo);
    }

    public function pagarMulta(Request $request, Prestamo $prestamo)
    {
        if ($prestamo->multa_estado !== 'pendiente') {
            return response()->json(['message' => 'Este préstamo no tiene una multa pendiente'], 409);
        }

        $staff = $request->user();

        $prestamo->update([
            'multa_estado' => 'pagada',
            'multa_pagada_en' => now(),
            'multa_pagada_por' => $staff->nombre,
            'multa_pagada_por_staff_id' => $staff->id,
        ]);

        return response()->json($prestamo);
    }
}
