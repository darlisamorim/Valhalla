<?php

namespace Database\Seeders;

use App\Models\Loja;
use Illuminate\Database\Seeder;

/**
 * A loja única (STORE_MODE=single).
 *
 * No modo loja única precisa existir exatamente uma loja — é ela que o SingleStoreResolver
 * devolve em `Loja::atual()`. Idempotente: rodar de novo não cria uma segunda.
 */
class LojaPadraoSeeder extends Seeder
{
    public function run(): void
    {
        if (Loja::query()->exists()) {
            return;
        }

        Loja::query()->create([
            'nome' => config('app.name', 'Minha Loja'),
            'slug' => 'loja-padrao',
            'ativa' => true,
        ]);
    }
}
