<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = [
        'sala_id',
        'usuario_id',
        'rut_usuario',
        'cantidad_personas',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
        'prestado_por',
        'devuelto_por',
        'hora_prestamo_real',
        'hora_devolucion_real',
        'via',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'hora_prestamo_real' => 'datetime',
            'hora_devolucion_real' => 'datetime',
        ];
    }

    public function sala()
    {
        return $this->belongsTo(Sala::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function participantes()
    {
        return $this->belongsToMany(Usuario::class, 'reserva_participantes')->withTimestamps();
    }

    /**
     * Plazo máximo para que el grupo se presente a confirmar la reserva: 15 minutos
     * desde que comienza el bloque reservado — salvo que la reserva se haya creado
     * DESPUÉS de que el bloque ya empezó (ej. "Reservar ahora" a las 15:00 dentro de un
     * bloque 14:00-16:00), en cuyo caso el plazo corre desde el momento de la reserva,
     * no desde el inicio nominal del bloque (que ya pasó).
     */
    public function plazoConfirmacion(): Carbon
    {
        $inicioBloque = Carbon::parse($this->fecha->toDateString())->setTime($this->hora_inicio, 0, 0);
        $base = $this->created_at->greaterThan($inicioBloque) ? $this->created_at : $inicioBloque;

        return $base->copy()->addMinutes(15);
    }

    public function estaVencidaSinConfirmar(): bool
    {
        return $this->estado === 'activa'
            && ! $this->hora_prestamo_real
            && now()->greaterThan($this->plazoConfirmacion());
    }
}
