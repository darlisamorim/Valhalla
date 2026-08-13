<?php

namespace Tests\Feature\Nucleo;

use App\Models\Loja;
use App\Nucleo\Loja\NenhumaLojaConfigurada;
use App\Nucleo\Loja\ResolvedorDeLoja;
use App\Nucleo\Loja\SingleStoreResolver;
use Database\Seeders\LojaPadraoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Loja::atual() e o resolvedor (CLAUDE.md 5.1, 5.2 e regra de ouro 3).
 */
class LojaAtualTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_modo_single_o_driver_de_fabrica_e_o_single_store_resolver(): void
    {
        $this->assertSame('single', config('app.store_mode'));
        $this->assertInstanceOf(SingleStoreResolver::class, app(ResolvedorDeLoja::class));
    }

    public function test_loja_atual_devolve_a_unica_loja(): void
    {
        $loja = Loja::factory()->create(['nome' => 'Loja Teste']);

        $this->assertTrue(Loja::atual()->is($loja));
        $this->assertSame('Loja Teste', Loja::atual()->nome);
    }

    public function test_loja_atual_estoura_quando_nao_ha_loja(): void
    {
        $this->expectException(NenhumaLojaConfigurada::class);

        Loja::atual();
    }

    /**
     * Quem sabe lidar com a ausência usa atualOuNula() — e não recebe exceção.
     */
    public function test_atual_ou_nula_devolve_null_quando_nao_ha_loja(): void
    {
        $this->assertNull(Loja::atualOuNula());
    }

    public function test_a_resolucao_e_memoizada_na_requisicao(): void
    {
        Loja::factory()->create();

        $primeira = Loja::atual();
        $segunda = Loja::atual();

        // Mesma instância: não foi ao banco de novo.
        $this->assertSame($primeira, $segunda);
    }

    public function test_tornar_atual_troca_a_loja_resolvida(): void
    {
        $primeira = Loja::factory()->create();
        $segunda = Loja::factory()->create();

        $this->assertTrue(Loja::atual()->is($primeira));

        $segunda->tornarAtual();

        $this->assertTrue(Loja::atual()->is($segunda));
    }

    public function test_esquecer_forca_nova_resolucao(): void
    {
        $primeira = Loja::factory()->create();
        $segunda = Loja::factory()->create();

        $segunda->tornarAtual();
        app(ResolvedorDeLoja::class)->esquecer();

        // Voltou a resolver do banco: a primeira loja (menor id).
        $this->assertTrue(Loja::atual()->is($primeira));
    }

    /**
     * O seeder da loja única é idempotente — rodar duas vezes não cria uma segunda loja,
     * o que quebraria a premissa do modo single.
     */
    public function test_seeder_da_loja_padrao_e_idempotente(): void
    {
        $this->seed(LojaPadraoSeeder::class);
        $this->seed(LojaPadraoSeeder::class);

        $this->assertSame(1, Loja::query()->count());
    }
}
