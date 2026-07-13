<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Libro extends Model
{
    use HasFactory;

    protected $table = 'libros';

    protected $fillable = [
        'codigo_barras',
        'titulo',
        'autor',
        'categoria',
        'disponible',
        'clasificacion',
        'coleccion',
        'editorial',
        'anio_publicacion',
        'ubicacion',
        'tipo_material',
        'volumen',
        'nota_interna',
        'nota_publica',
        'precio',
        'estado_proceso',
        'fecha_inventario',
    ];

    protected function casts(): array
    {
        return [
            'disponible' => 'boolean',
            'anio_publicacion' => 'integer',
            'precio' => 'decimal:2',
            'fecha_inventario' => 'date',
        ];
    }

    public function reservas()
    {
        return $this->hasMany(ReservaLibro::class);
    }
}
