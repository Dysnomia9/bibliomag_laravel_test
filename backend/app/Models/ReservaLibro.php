<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservaLibro extends Model
{
    use HasFactory;

    protected $table = 'reservas_libro';

    protected $fillable = [
        'usuario_id',
        'libro_id',
        'fecha_reserva',
        'fecha_retiro',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_reserva' => 'date',
            'fecha_retiro' => 'date',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function libro()
    {
        return $this->belongsTo(Libro::class);
    }
}
