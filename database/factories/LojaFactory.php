<?php

namespace Database\Factories;

use App\Models\Loja;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Loja>
 */
class LojaFactory extends Factory
{
    protected $model = Loja::class;

    public function definition(): array
    {
        $nome = fake()->unique()->company();

        return [
            'nome' => $nome,
            'slug' => Str::slug($nome).'-'.fake()->unique()->numberBetween(1, 999999),
            'dominio' => null,
            'ativa' => true,
            'configuracoes' => null,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn () => ['ativa' => false]);
    }
}
