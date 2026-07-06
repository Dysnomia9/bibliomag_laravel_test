<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entrada extends Model
{
    use HasFactory;

    protected $table = 'entradas';

    protected $fillable = [
        'usuario_id',
        'rut_externo',
        'nombre_externo',
        'fecha_hora_entrada',
        'fecha_hora_salida',
        'via',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora_entrada' => 'datetime',
            'fecha_hora_salida' => 'datetime',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
