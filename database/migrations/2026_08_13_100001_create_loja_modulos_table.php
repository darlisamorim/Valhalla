<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de módulos ativos por loja (CLAUDE.md 5.3). O lojista liga/desliga pelo painel;
 * a combinação de módulos ligados É o plano comercial (CLAUDE.md 15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loja_modulos', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->foreignId('loja_id')->constrained('lojas')->cascadeOnDelete();

            // Valor do enum App\Nucleo\Modulos\Modulo — lista fechada e nominal.
            $tabela->string('modulo');

            $tabela->boolean('ativo')->default(false);

            // Config própria do módulo naquela loja (credenciais de gateway, etc).
            $tabela->json('configuracoes')->nullable();

            $tabela->timestamps();

            $tabela->unique(['loja_id', 'modulo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loja_modulos');
    }
};
