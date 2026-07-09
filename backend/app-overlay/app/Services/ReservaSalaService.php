<?php

namespace App\Services;

use App\Models\Reserva;

class ReservaSalaService
{
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
