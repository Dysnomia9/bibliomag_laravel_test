<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\Sala;

class ReservaSalaService
{
    /**
     * Registra el escaneo de un código de barras de logia (Horizon): la primera vez marca
     * la entrega de la reserva vigente para ese bloque horario, la segunda marca la
     * devolución. No crea reservas — solo cierra el ciclo de una reserva ya existente.
     */
    public function escanearLogia(string $codigoBarras, string $registradoPor): Reserva
    {
        $sala = Sala::where('codigo_barras', $codigoBarras)->where('tipo', 'logia')->first();

        if (! $sala) {
            throw new \RuntimeException('Código de barras no corresponde a ninguna logia');
        }

        $ahora = now();
        $reserva = Reserva::where('sala_id', $sala->id)
            ->where('fecha', $ahora->toDateString())
            ->where('hora_inicio', '<=', $ahora->hour)
            ->where('hora_fin', '>', $ahora->hour)
            ->where('estado', 'activa')
            ->first();

        if (! $reserva) {
            throw new \RuntimeException('Esta logia no tiene una reserva vigente en este momento');
        }

        if (! $reserva->hora_prestamo_real) {
            $reserva->update([
                'prestado_por' => $registradoPor,
                'hora_prestamo_real' => $ahora,
                'via' => 'BC',
            ]);
        } elseif (! $reserva->hora_devolucion_real) {
            $reserva->update([
                'devuelto_por' => $registradoPor,
                'hora_devolucion_real' => $ahora,
                'estado' => 'finalizada',
            ]);
        } else {
            throw new \RuntimeException('Esta reserva ya fue entregada y devuelta');
        }

        return $reserva->fresh();
    }

    public function existeSolapamiento(int $salaId, string $fecha, int $horaInicio, int $horaFin, ?int $ignorarReservaId = null): bool
    {
        return Reserva::where('sala_id', $salaId)
            ->where('fecha', $fecha)
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio)
            ->when($ignorarReservaId, fn ($query) => $query->where('id', '!=', $ignorarReservaId))
            ->exists();
    }

    /**
     * Devuelve el primer RUT que ya participa en otra reserva (en cualquier sala)
     * cuyo horario se solape con el bloque solicitado, o null si ninguno choca.
     */
    public function participanteConReservaSolapada(array $ruts, string $fecha, int $horaInicio, int $horaFin, ?int $ignorarReservaId = null): ?string
    {
        foreach ($ruts as $rut) {
            $existe = Reserva::where('fecha', $fecha)
                ->where('hora_inicio', '<', $horaFin)
                ->where('hora_fin', '>', $horaInicio)
                ->whereJsonContains('ruts', $rut)
                ->when($ignorarReservaId, fn ($query) => $query->where('id', '!=', $ignorarReservaId))
                ->exists();

            if ($existe) {
                return $rut;
            }
        }

        return null;
    }
}
