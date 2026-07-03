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
    ];

    protected function casts(): array
    {
        return [
            'disponible' => 'boolean',
        ];
    }

    public function reservas()
    {
        return $this->hasMany(ReservaLibro::class);
    }
}
