<?php

namespace Database\Factories;

use App\Models\Libro;
use Illuminate\Database\Eloquent\Factories\Factory;

class LibroFactory extends Factory
{
    protected $model = Libro::class;

    public function definition(): array
    {
        return [
            'codigo_barras' => fake()->unique()->ean13(),
            'titulo' => fake()->sentence(3),
            'autor' => fake()->name(),
            'categoria' => fake()->randomElement(['Novela', 'Ciencia', 'Historia', 'Tecnología']),
            'disponible' => true,
        ];
    }
}
