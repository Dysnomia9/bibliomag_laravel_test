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
        'nombre_usuario',
        'rut_usuario',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
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
