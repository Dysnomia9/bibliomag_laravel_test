<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\Sala;
use App\Models\Staff;
use App\Models\Usuario;

class ReservaSalaService
{
    /**
     * Registra el escaneo de un código de barras de logia (Horizon): la primera vez marca
     * la entrega de la reserva vigente para ese bloque horario, la segunda marca la
     * devolución. No crea reservas  solo cierra el ciclo de una reserva ya existente.
     */
    public function escanearLogia(string $codigoBarras, Staff $staff): Reserva
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
                'prestado_por' => $staff->nombre,
                'prestado_por_staff_id' => $staff->id,
                'hora_prestamo_real' => $ahora,
                'via' => 'BC',
            ]);
        } elseif (! $reserva->hora_devolucion_real) {
            $reserva->update([
                'devuelto_por' => $staff->nombre,
                'devuelto_por_staff_id' => $staff->id,
                'hora_devolucion_real' => $ahora,
                'estado' => 'finalizada',
            ]);
        } else {
            throw new \RuntimeException('Esta reserva ya fue entregada y devuelta');
        }

        return $reserva->fresh();
    }

    /**
     * Confirma manualmente (desde el panel de personal, sin escaneo de código de barras)
     * que la llave de una logia fue devuelta — equivalente a la segunda mitad de
     * escanearLogia(), pero identificando la reserva directamente por id en vez de por
     * código de barras + bloque horario vigente. A diferencia de cancelar (que elimina la
     * reserva), esto conserva el registro con quién y cuándo se devolvió la llave.
     */
    public function registrarDevolucion(Reserva $reserva, Staff $staff): Reserva
    {
        if ($reserva->hora_devolucion_real) {
            throw new \RuntimeException('Esta reserva ya tiene registrada su devolución');
        }

        $reserva->update([
            'devuelto_por' => $staff->nombre,
            'devuelto_por_staff_id' => $staff->id,
            'hora_devolucion_real' => now(),
            'estado' => 'finalizada',
        ]);

        return $reserva->fresh();
    }

    /**
     * Confirma que el grupo se presentó a retirar la sala (check-in manual, sin código de
     * barras — sirve tanto para logias como para las salas con nombre propio que no
     * tienen codigo_barras). Rechaza la confirmación si ya pasó el plazo de 15 minutos
     * (ver Reserva::plazoConfirmacion()) y en ese caso libera la reserva de una vez
     * (mismo efecto que liberarPorNoPresentacion()) para que quede disponible de inmediato.
     */
    public function registrarLlegada(Reserva $reserva, Staff $staff): Reserva
    {
        if ($reserva->hora_prestamo_real) {
            throw new \RuntimeException('Esta reserva ya tiene registrada su llegada');
        }

        if ($reserva->estaVencidaSinConfirmar()) {
            $reserva->update(['estado' => 'no_show']);
            throw new \RuntimeException('El plazo de 15 minutos para confirmar esta reserva ya venció — la sala quedó liberada');
        }

        $reserva->update([
            'prestado_por' => $staff->nombre,
            'prestado_por_staff_id' => $staff->id,
            'hora_prestamo_real' => now(),
            'via' => 'manual',
        ]);

        return $reserva->fresh();
    }

    /**
     * Acción manual del staff para liberar de inmediato una reserva vencida sin
     * confirmar, sin esperar a que alguien más intente reservar ese mismo bloque (que
     * es cuando existeSolapamiento() la liberaría de todos modos, de forma perezosa).
     */
    public function liberarPorNoPresentacion(Reserva $reserva): Reserva
    {
        if (! $reserva->estaVencidaSinConfirmar()) {
            throw new \RuntimeException('Esta reserva todavía está dentro del plazo de confirmación');
        }

        $reserva->update(['estado' => 'no_show']);

        return $reserva->fresh();
    }

    /** Si la reserva está vencida sin confirmar, la marca 'no_show' (libera el bloque) y devuelve true. */
    public function liberarSiVencida(Reserva $reserva): bool
    {
        if (! $reserva->estaVencidaSinConfirmar()) {
            return false;
        }

        $reserva->update(['estado' => 'no_show']);

        return true;
    }

    public function existeSolapamiento(int $salaId, string $fecha, int $horaInicio, int $horaFin, ?int $ignorarReservaId = null): bool
    {
        $candidatas = Reserva::where('sala_id', $salaId)
            ->where('fecha', $fecha)
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio)
            ->when($ignorarReservaId, fn ($query) => $query->where('id', '!=', $ignorarReservaId))
            ->get();

        foreach ($candidatas as $candidata) {
            if (! $this->liberarSiVencida($candidata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Un mismo participante no puede tener reservas 'activa' en salas distintas ni en
     * bloques no adyacentes de la misma sala el mismo día — solo puede "extender" su
     * estadía al bloque inmediatamente anterior o siguiente de la sala que ya tiene
     * reservada, y como máximo una vez (no ambas direcciones a la vez). Las reservas
     * ya 'finalizada' (llave devuelta) o 'no_show' no cuentan — una vez que alguien
     * terminó, puede volver a reservar libremente más tarde ese mismo día. Devuelve
     * `[rut, motivo]` del primer participante que violaría la regla, o null si todos
     * pueden reservar sin problema.
     *
     * @return array{0: string, 1: 'otra_sala'|'no_adyacente'|'limite'}|null
     */
    public function participanteExcedeLimiteDeBloques(array $ruts, int $salaId, string $fecha, int $horaInicio, int $horaFin, ?int $ignorarReservaId = null): ?array
    {
        $idPorRut = Usuario::whereIn('rut', $ruts)->pluck('id', 'rut');

        foreach ($ruts as $rut) {
            $usuarioId = $idPorRut->get($rut);
            if (! $usuarioId) {
                continue; // RUT inválido — ya lo rechaza la regla 'exists' del validator.
            }

            $reservasActivas = Reserva::where('fecha', $fecha)
                ->where('estado', 'activa')
                ->whereHas('participantes', fn ($q) => $q->where('usuarios.id', $usuarioId))
                ->when($ignorarReservaId, fn ($q) => $q->where('id', '!=', $ignorarReservaId))
                ->get()
                ->reject(fn (Reserva $r) => $this->liberarSiVencida($r))
                ->values();

            if ($reservasActivas->isEmpty()) {
                continue;
            }

            if ($reservasActivas->count() >= 2) {
                return [$rut, 'limite'];
            }

            $existente = $reservasActivas->first();
            $mismaSala = (int) $existente->sala_id === $salaId;
            $esAdyacente = (int) $existente->hora_fin === $horaInicio || (int) $existente->hora_inicio === $horaFin;

            if (! $mismaSala) {
                return [$rut, 'otra_sala'];
            }

            if (! $esAdyacente) {
                return [$rut, 'no_adyacente'];
            }
        }

        return null;
    }

    /**
     * Arma el mensaje 409 para participanteExcedeLimiteDeBloques() — en 2ª persona
     * ("Ya tienes...") si el RUT que violó la regla es el del propio usuario
     * autenticado (portal), o en 3ª persona ("El RUT X ya tiene...") en cualquier
     * otro caso (staff reservando para un grupo). Mismo criterio que el mensaje de
     * participanteConReservaSolapada en PortalController::reservarSala().
     *
     * @param array{0: string, 1: 'otra_sala'|'no_adyacente'|'limite'} $excedeLimite
     */
    public function mensajeLimiteBloques(array $excedeLimite, ?string $rutPropio = null): string
    {
        [$rut, $motivo] = $excedeLimite;

        if ($rut === $rutPropio) {
            return match ($motivo) {
                'otra_sala' => 'Ya tienes una reserva activa en otra sala hoy — solo puedes reservar el bloque anterior o siguiente en la misma sala.',
                'no_adyacente' => 'Ya tienes una reserva activa en esta sala en un bloque no consecutivo — solo puedes agregar el bloque inmediatamente anterior o siguiente.',
                default => 'Ya alcanzaste el máximo de bloques reservados por hoy (2, en la misma sala).',
            };
        }

        return match ($motivo) {
            'otra_sala' => "El RUT {$rut} ya tiene una reserva activa en otra sala hoy — solo puede reservar el bloque anterior o siguiente en la misma sala.",
            'no_adyacente' => "El RUT {$rut} ya tiene una reserva activa en esta sala en un bloque no consecutivo — solo puede agregar el bloque inmediatamente anterior o siguiente.",
            default => "El RUT {$rut} ya alcanzó el máximo de bloques reservados por hoy (2, en la misma sala).",
        };
    }

    /**
     * Devuelve el primer RUT que ya participa en otra reserva (en cualquier sala)
     * cuyo horario se solape con el bloque solicitado, o null si ninguno choca.
     */
    public function participanteConReservaSolapada(array $ruts, string $fecha, int $horaInicio, int $horaFin, ?int $ignorarReservaId = null): ?string
    {
        $idPorRut = Usuario::whereIn('rut', $ruts)->pluck('id', 'rut');

        $idsConflicto = Reserva::where('fecha', $fecha)
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio)
            ->when($ignorarReservaId, fn ($query) => $query->where('id', '!=', $ignorarReservaId))
            ->whereHas('participantes', fn ($q) => $q->whereIn('usuarios.id', $idPorRut->values()))
            ->with(['participantes' => fn ($q) => $q->whereIn('usuarios.id', $idPorRut->values())])
            ->get()
            ->reject(fn (Reserva $r) => $this->liberarSiVencida($r))
            ->flatMap(fn ($r) => $r->participantes->pluck('id'))
            ->unique();

        foreach ($ruts as $rut) {
            if ($idsConflicto->contains($idPorRut->get($rut))) {
                return $rut;
            }
        }

        return null;
    }
}
