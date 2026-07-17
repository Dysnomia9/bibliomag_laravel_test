<?php

namespace Database\Factories;

use App\Models\Equipo;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipoFactory extends Factory
{
    protected $model = Equipo::class;

    public function definition(): array
    {
        return [
            'codigo_inventario' => strtoupper(fake()->unique()->bothify('EQ-###')),
            'tipo' => fake()->randomElement(['audifonos', 'notebook']),
            'disponible' => true,
            'activo' => true,
        ];
    }
}
