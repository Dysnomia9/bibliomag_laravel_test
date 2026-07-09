<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrada;
use App\Models\Prestamo;
use App\Models\Reserva;
use App\Models\Usuario;
use App\Support\Reportes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReporteController extends Controller
{
    public function opciones()
    {
        return response()->json([
            'carreras' => Usuario::whereNotNull('carrera')->distinct()->orderBy('carrera')->pluck('carrera'),
            'aniosIngreso' => Usuario::whereNotNull('anio_ingreso')->distinct()->orderBy('anio_ingreso')->pluck('anio_ingreso'),
            'sexos' => Usuario::whereNotNull('sexo')->distinct()->orderBy('sexo')->pluck('sexo'),
            'tiposUsuario' => ['estudiante', 'docente', 'funcionario'],
        ]);
    }

    public function resumen(Request $request)
    {
        $tab = $request->query('tab', 'prestamos');
        $periodo = $request->query('periodo', 'mes');

        $aplicarFiltros = function ($query) use ($request) {
            if ($carrera = $request->query('carrera')) {
                $query->whereHas('usuario', fn ($u) => $u->where('carrera', $carrera));
            }
            if ($anio = $request->query('anio_ingreso')) {
                $query->whereHas('usuario', fn ($u) => $u->where('anio_ingreso', $anio));
            }
            if ($sexo = $request->query('sexo')) {
                $query->whereHas('usuario', fn ($u) => $u->where('sexo', $sexo));
            }
            if ($tipo = $request->query('tipo_usuario')) {
                $query->whereHas('usuario', fn ($u) => $u->where('tipo', $tipo));
            }
        };

        $registros = match ($tab) {
            'ingresos' => (function () use ($aplicarFiltros) {
                $query = Entrada::with('usuario');
                $aplicarFiltros($query);

                return $query->get()->map(fn ($e) => [
                    'fecha' => Carbon::parse($e->fecha_hora_entrada),
                    'hora' => Carbon::parse($e->fecha_hora_entrada)->hour,
                    'carrera' => $e->usuario->carrera ?? 'Sin dato',
                    'anioIngreso' => $e->usuario->anio_ingreso ?? 'Sin dato',
                    'sexo' => $e->usuario->sexo ?? 'Sin dato',
                    'tipoUsuario' => $e->usuario->tipo ?? 'Sin dato',
                ]);
            })(),
            'logias' => (function () use ($aplicarFiltros) {
                $query = Reserva::with('usuario');
                $aplicarFiltros($query);

                return $query->get()->map(fn ($r) => [
                    'fecha' => Carbon::parse($r->fecha),
                    'hora' => (int) $r->hora_inicio,
                    'carrera' => $r->usuario->carrera ?? 'Sin dato',
                    'anioIngreso' => $r->usuario->anio_ingreso ?? 'Sin dato',
                    'sexo' => $r->usuario->sexo ?? 'Sin dato',
                    'tipoUsuario' => $r->usuario->tipo ?? 'Sin dato',
                ]);
            })(),
            default => (function () use ($aplicarFiltros) {
                $query = Prestamo::with('usuario');
                $aplicarFiltros($query);

                return $query->get()->map(fn ($p) => [
                    'fecha' => Carbon::parse($p->fecha_prestamo),
                    'hora' => Carbon::parse($p->fecha_prestamo)->hour,
                    'carrera' => $p->usuario->carrera ?? 'Sin dato',
                    'anioIngreso' => $p->usuario->anio_ingreso ?? 'Sin dato',
                    'sexo' => $p->usuario->sexo ?? 'Sin dato',
                    'tipoUsuario' => $p->usuario->tipo ?? 'Sin dato',
                ]);
            })(),
        };

        $serieMap = [];
        foreach ($registros as $r) {
            $key = Reportes::bucketKey($r['fecha'], $periodo);
            $serieMap[$key] = ($serieMap[$key] ?? 0) + 1;
        }
        ksort($serieMap);
        $serie = collect($serieMap)
            ->slice(-18)
            ->map(fn ($value, $key) => ['label' => Reportes::bucketLabel($key, $periodo), 'value' => $value])
            ->values();

        $groupBy = function ($campo) use ($registros) {
            return $registros
                ->groupBy($campo)
                ->map(fn ($grupo, $label) => ['label' => (string) $label, 'value' => $grupo->count()])
                ->values()
                ->sortByDesc('value')
                ->values();
        };

        $porCarrera = $groupBy('carrera')->take(8);
        $porSexo = $groupBy('sexo');
        $porAnioIngreso = $groupBy('anioIngreso')->sortBy('label')->values();
        $porTipoUsuario = $groupBy('tipoUsuario');

        // no tiene sentido mostrar horas
        $horaApertura = 8;
        $horaCierre = 21;
        $horaMap = array_fill_keys(range($horaApertura, $horaCierre), 0);
        foreach ($registros as $r) {
            if ($r['hora'] >= $horaApertura && $r['hora'] <= $horaCierre) {
                $horaMap[$r['hora']]++;
            }
        }
        $porHora = collect($horaMap)->map(fn ($value, $hora) => [
            'label' => str_pad((string) $hora, 2, '0', STR_PAD_LEFT).'h',
            'value' => $value,
        ])->values();

        $total = $registros->count();
        $numBuckets = max(1, $serie->count());

        return response()->json([
            'total' => $total,
            'promedioPorPeriodo' => (int) round($total / $numBuckets),
            'categoriaMasFrecuente' => $porCarrera->first()['label'] ?? '—',
            'serie' => $serie,
            'porCarrera' => $porCarrera,
            'porSexo' => $porSexo,
            'porAnioIngreso' => $porAnioIngreso,
            'porTipoUsuario' => $porTipoUsuario,
            'porHora' => $porHora,
        ]);
    }
}
