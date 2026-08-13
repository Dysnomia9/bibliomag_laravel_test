<?php

namespace App\Services;

use App\Models\Libro;
use App\Models\ReservaLibro;
use Illuminate\Support\Facades\DB;

/**
 * Regla de negocio compartida entre ReservaLibroController (staff) y
 * PortalReservaLibroController (autoservicio del portal virtual) — antes
 * vivía duplicada la mitad de esta lógica en ReservaLibroController, y no
 * existía en absoluto la cola de espera (reservar un libro no disponible
 * devolvía 409 sin ninguna alternativa).
 */
class ReservaLibroService
{
    /**
     * @return array{0: ReservaLibro|null, 1: string|null, 2: int}
     */
    public function reservarOEncolar(string $codigoBarras, int $usuarioId, ?string $fechaReserva = null, ?string $fechaRetiro = null): array
    {
        return DB::transaction(function () use ($codigoBarras, $usuarioId, $fechaReserva, $fechaRetiro) {
            $libro = Libro::where('codigo_barras', $codigoBarras)->lockForUpdate()->first();

            if (! $libro) {
                return [null, 'Código de barras no encontrado en el sistema', 404];
            }

            if ($libro->estado_proceso !== 'en_estante') {
                return [null, "Este libro no está disponible para préstamo (estado: {$libro->estado_proceso})", 409];
            }

            $yaActiva = ReservaLibro::where('libro_id', $libro->id)
                ->where('usuario_id', $usuarioId)
                ->whereIn('estado', ['pendiente', 'en_cola'])
                ->exists();

            if ($yaActiva) {
                return [null, 'Ya tienes una reserva o un lugar en la cola de espera activo para este libro', 409];
            }

            if ($libro->disponible) {
                $reserva = ReservaLibro::create([
                    'usuario_id' => $usuarioId,
                    'libro_id' => $libro->id,
                    'fecha_reserva' => $fechaReserva ?? now()->toDateString(),
                    'fecha_retiro' => $fechaRetiro ?? now()->addDays(config('reservas_libro.dias_para_retirar'))->toDateString(),
                    'estado' => 'pendiente',
                ]);

                $libro->update(['disponible' => false]);

                return [$reserva->load('libro'), null, 201];
            }

            // Libro ocupado: se une a la cola de espera (FIFO por created_at) en vez de
            // rechazar la reserva. fecha_retiro queda null hasta que se lo promueva.
            $reserva = ReservaLibro::create([
                'usuario_id' => $usuarioId,
                'libro_id' => $libro->id,
                'fecha_reserva' => $fechaReserva ?? now()->toDateString(),
                'fecha_retiro' => null,
                'estado' => 'en_cola',
            ]);

            return [$reserva->load('libro'), null, 201];
        });
    }

    public function cancelar(ReservaLibro $reserva): void
    {
        DB::transaction(function () use ($reserva) {
            $liberaLibro = $reserva->estado === 'pendiente';

            $reserva->update(['estado' => 'cancelado']);

            // Si estaba 'en_cola' (nunca llegó a tener el libro apartado), cancelar no
            // afecta la disponibilidad de nadie más — simplemente sale de la fila.
            if ($liberaLibro) {
                $libro = Libro::whereKey($reserva->libro_id)->lockForUpdate()->first();
                if ($libro) {
                    $this->liberarLibro($libro);
                }
            }
        });
    }

    /**
     * Se llama siempre que un libro pasa a estar disponible: al devolver un préstamo
     * (PrestamoController::devolver()) o al cancelar una reserva 'pendiente'. En vez de
     * dejarlo simplemente disponible, promueve a la primera persona en la cola de espera
     * (FIFO por created_at) si hay alguien — el libro sigue "no disponible", ahora
     * apartado para esa persona, con una fecha_retiro nueva desde este momento.
     */
    public function liberarLibro(Libro $libro): void
    {
        // orderBy('id') en vez de created_at: dos filas creadas en el mismo segundo
        // tendrían el mismo timestamp y empatarían el orden FIFO; el id autoincremental
        // no tiene esa ambigüedad.
        $siguiente = ReservaLibro::where('libro_id', $libro->id)
            ->where('estado', 'en_cola')
            ->oldest('id')
            ->lockForUpdate()
            ->first();

        if (! $siguiente) {
            $libro->update(['disponible' => true]);

            return;
        }

        $siguiente->update([
            'estado' => 'pendiente',
            'fecha_retiro' => now()->addDays(config('reservas_libro.dias_para_retirar'))->toDateString(),
        ]);
    }
}
