<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = [
        'sala_id',
        'usuario_id',
        'rut_usuario',
        'cantidad_personas',
        'ruts',
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
            'ruts' => 'array',
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
}
