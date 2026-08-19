<?php

namespace App\Services;

use App\Models\Ejemplar;
use App\Models\ReservaLibro;
use App\Notifications\ReservaListaParaRetirarNotification;
use Illuminate\Support\Facades\DB;

/**
 * Regla de negocio compartida entre ReservaLibroController (staff) y
 * PortalReservaLibroController (autoservicio del portal virtual). Opera
 * sobre `Ejemplar` (copia física, identificada por código de barras) — la
 * reserva en sí (`ReservaLibro`) guarda tanto `libro_id` (la obra deseada,
 * siempre presente) como `ejemplar_id` (la copia física asignada, null
 * mientras está 'en_cola': todavía no se le asignó copia).
 */
class ReservaLibroService
{
    /**
     * @return array{0: ReservaLibro|null, 1: string|null, 2: int}
     */
    public function reservarOEncolar(string $codigoBarras, int $usuarioId, ?string $fechaReserva = null, ?string $fechaRetiro = null, ?int $registradoPorStaffId = null): array
    {
        return DB::transaction(function () use ($codigoBarras, $usuarioId, $fechaReserva, $fechaRetiro, $registradoPorStaffId) {
            $ejemplar = Ejemplar::where('codigo_barras', $codigoBarras)->lockForUpdate()->first();

            if (! $ejemplar) {
                return [null, 'Código de barras no encontrado en el sistema', 404];
            }

            return $this->procesarReserva($ejemplar, $usuarioId, $fechaReserva, $fechaRetiro, $registradoPorStaffId);
        });
    }

    /**
     * Igual que reservarOEncolar(), pero para el portal virtual: el usuario navega el
     * catálogo por título (obra), no escanea un código de barras de una copia
     * concreta — no le importa cuál copia física le toque, cualquiera de las de ese
     * libro sirve. Se elige una con preferencia por una que esté disponible ahora
     * mismo; si ninguna lo está, se toma cualquiera en estante para unirse a la cola
     * (la cola ya está compartida entre todas las copias de un mismo libro_id — ver
     * liberarLibro()).
     *
     * @return array{0: ReservaLibro|null, 1: string|null, 2: int}
     */
    public function reservarOEncolarPorLibro(int $libroId, int $usuarioId, ?string $fechaReserva = null, ?string $fechaRetiro = null): array
    {
        return DB::transaction(function () use ($libroId, $usuarioId, $fechaReserva, $fechaRetiro) {
            $ejemplar = Ejemplar::where('libro_id', $libroId)
                ->where('estado_proceso', 'en_estante')
                ->orderByDesc('disponible')
                ->lockForUpdate()
                ->first();

            if (! $ejemplar) {
                return [null, 'Este libro no tiene copias disponibles para préstamo', 404];
            }

            return $this->procesarReserva($ejemplar, $usuarioId, $fechaReserva, $fechaRetiro);
        });
    }

    /** @return array{0: ReservaLibro|null, 1: string|null, 2: int} */
    private function procesarReserva(Ejemplar $ejemplar, int $usuarioId, ?string $fechaReserva, ?string $fechaRetiro, ?int $registradoPorStaffId = null): array
    {
        if ($ejemplar->estado_proceso !== 'en_estante') {
            return [null, "Este libro no está disponible para préstamo (estado: {$ejemplar->estado_proceso})", 409];
        }

        $yaActiva = ReservaLibro::where('libro_id', $ejemplar->libro_id)
            ->where('usuario_id', $usuarioId)
            ->whereIn('estado', ['pendiente', 'en_cola'])
            ->exists();

        if ($yaActiva) {
            return [null, 'Ya tienes una reserva o un lugar en la cola de espera activo para este libro', 409];
        }

        if ($ejemplar->disponible) {
            $reserva = ReservaLibro::create([
                'usuario_id' => $usuarioId,
                'libro_id' => $ejemplar->libro_id,
                'ejemplar_id' => $ejemplar->id,
                'fecha_reserva' => $fechaReserva ?? now()->toDateString(),
                'fecha_retiro' => $fechaRetiro ?? now()->addDays(config('reservas_libro.dias_para_retirar'))->toDateString(),
                'estado' => 'pendiente',
                'registrado_por_staff_id' => $registradoPorStaffId,
            ]);

            $ejemplar->update(['disponible' => false]);

            return [$reserva->load('libro', 'ejemplar'), null, 201];
        }

        // Copia ocupada: se une a la cola de espera (FIFO por id) en vez de
        // rechazar la reserva. ejemplar_id/fecha_retiro quedan null hasta que se
        // le asigne una copia (ver liberarLibro()).
        $reserva = ReservaLibro::create([
            'usuario_id' => $usuarioId,
            'libro_id' => $ejemplar->libro_id,
            'ejemplar_id' => null,
            'fecha_reserva' => $fechaReserva ?? now()->toDateString(),
            'fecha_retiro' => null,
            'estado' => 'en_cola',
            'registrado_por_staff_id' => $registradoPorStaffId,
        ]);

        return [$reserva->load('libro'), null, 201];
    }

    public function cancelar(ReservaLibro $reserva): void
    {
        DB::transaction(function () use ($reserva) {
            $liberaEjemplar = $reserva->estado === 'pendiente';
            $ejemplarId = $reserva->ejemplar_id;

            $reserva->update(['estado' => 'cancelado']);

            // Si estaba 'en_cola' (nunca llegó a tener copia asignada), cancelar no
            // afecta la disponibilidad de nadie más — simplemente sale de la fila.
            if ($liberaEjemplar && $ejemplarId) {
                $ejemplar = Ejemplar::whereKey($ejemplarId)->lockForUpdate()->first();
                if ($ejemplar) {
                    $this->liberarLibro($ejemplar);
                }
            }
        });
    }

    /**
     * Se llama siempre que una copia pasa a estar disponible: al devolver un
     * préstamo (PrestamoController::devolver()) o al cancelar una reserva
     * 'pendiente'. En vez de dejarla simplemente disponible, promueve a la
     * primera persona en la cola de espera de ese título (FIFO por id) si hay
     * alguien — la copia sigue "no disponible", ahora apartada para esa
     * persona, con una fecha_retiro nueva desde este momento.
     */
    public function liberarLibro(Ejemplar $ejemplar): void
    {
        // orderBy('id') en vez de created_at: dos filas creadas en el mismo segundo
        // tendrían el mismo timestamp y empatarían el orden FIFO; el id autoincremental
        // no tiene esa ambigüedad.
        $siguiente = ReservaLibro::where('libro_id', $ejemplar->libro_id)
            ->where('estado', 'en_cola')
            ->oldest('id')
            ->lockForUpdate()
            ->first();

        if (! $siguiente) {
            $ejemplar->update(['disponible' => true]);

            return;
        }

        $siguiente->update([
            'estado' => 'pendiente',
            'ejemplar_id' => $ejemplar->id,
            'fecha_retiro' => now()->addDays(config('reservas_libro.dias_para_retirar'))->toDateString(),
        ]);

        // Sin SMTP configurado (ver .env.example), esto solo queda escrito en
        // storage/logs/laravel.log — no falla ni intenta salir a la red.
        $siguiente->load(['usuario', 'libro']);
        if ($siguiente->usuario?->email) {
            $siguiente->usuario->notify(new ReservaListaParaRetirarNotification($siguiente));
        }
    }
}
