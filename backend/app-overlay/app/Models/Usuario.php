<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    use HasFactory;

    protected $table = 'usuarios';

    protected $fillable = [
        'rut',
        'nombre',
        'apellido',
        'email',
        'tipo',
        'carrera',
        'anio_ingreso',
        'sexo',
        'activo',
        'qr_code',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function entradas()
    {
        return $this->hasMany(Entrada::class);
    }

    public function prestamos()
    {
        return $this->hasMany(Prestamo::class);
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }
}
