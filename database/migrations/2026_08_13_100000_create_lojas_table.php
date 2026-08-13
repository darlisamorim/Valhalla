<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A loja/tenant. Fonte da verdade de `loja_id` — toda entidade de negócio (produto,
 * pedido, cliente) pertence a uma loja (CLAUDE.md 5.1).
 *
 * No modo loja única existe exatamente uma linha aqui. Loja única é multi-loja com N=1:
 * a tabela é a mesma nas duas edições.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lojas', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->string('nome');
            $tabela->string('slug')->unique();

            // Só usado na edição SaaS, onde a loja é resolvida pelo domínio.
            $tabela->string('dominio')->nullable()->unique();

            $tabela->boolean('ativa')->default(true);

            // Tokens de tema, preferências do lojista, etc.
            $tabela->json('configuracoes')->nullable();

            $tabela->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lojas');
    }
};
