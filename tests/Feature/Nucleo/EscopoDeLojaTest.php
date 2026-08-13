<?php

namespace Tests\Feature\Nucleo;

use App\Models\Loja;
use App\Nucleo\Loja\NenhumaLojaConfigurada;
use App\Nucleo\Loja\PertenceALoja;
use App\Nucleo\Loja\ResolvedorDeLoja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Isolamento por padrão (CLAUDE.md 6): uma loja nunca enxerga o dado da outra, sem
 * ninguém precisar lembrar de filtrar.
 *
 * O teste usa uma entidade de mentira (`itens_de_teste`) de propósito: o trait tem que
 * funcionar para qualquer entidade de negócio que um módulo venha a criar, não para uma
 * tabela específica.
 */
class EscopoDeLojaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('itens_de_teste', function ($tabela) {
            $tabela->id();
            $tabela->foreignId('loja_id')->constrained('lojas')->cascadeOnDelete();
            $tabela->string('nome');
            $tabela->timestamps();
        });
    }

    public function test_consulta_so_devolve_dado_da_loja_atual(): void
    {
        $lojaA = Loja::factory()->create();
        $lojaB = Loja::factory()->create();

        ItemDeTeste::query()->create(['loja_id' => $lojaA->id, 'nome' => 'da A']);
        ItemDeTeste::query()->create(['loja_id' => $lojaB->id, 'nome' => 'da B']);

        $lojaA->tornarAtual();
        $this->assertSame(['da A'], ItemDeTeste::query()->pluck('nome')->all());

        $lojaB->tornarAtual();
        $this->assertSame(['da B'], ItemDeTeste::query()->pluck('nome')->all());
    }

    public function test_registro_criado_nasce_com_a_loja_atual(): void
    {
        $loja = Loja::factory()->create();
        $loja->tornarAtual();

        $item = ItemDeTeste::query()->create(['nome' => 'sem loja explícita']);

        $this->assertSame($loja->id, $item->loja_id);
        $this->assertTrue($item->loja->is($loja));
    }

    /**
     * Fecha em caso de dúvida: sem loja resolvida, a consulta não devolve nada — em vez de
     * devolver o dado de todas as lojas.
     */
    public function test_sem_loja_resolvida_a_consulta_nao_devolve_nada(): void
    {
        $loja = Loja::factory()->create();
        ItemDeTeste::query()->create(['loja_id' => $loja->id, 'nome' => 'existe']);

        // Nenhuma loja tornada atual e nenhuma resolvível: o resolver devolve null.
        $this->mock(ResolvedorDeLoja::class)
            ->shouldReceive('atual')->andReturn(null);

        $this->assertSame(0, ItemDeTeste::query()->count());
    }

    public function test_gravar_sem_loja_resolvida_estoura_em_vez_de_gravar_orfao(): void
    {
        $this->expectException(NenhumaLojaConfigurada::class);

        ItemDeTeste::query()->create(['nome' => 'órfão']);
    }

    public function test_painel_mestre_pode_furar_o_escopo_de_proposito(): void
    {
        $lojaA = Loja::factory()->create();
        $lojaB = Loja::factory()->create();

        ItemDeTeste::query()->create(['loja_id' => $lojaA->id, 'nome' => 'da A']);
        ItemDeTeste::query()->create(['loja_id' => $lojaB->id, 'nome' => 'da B']);

        $lojaA->tornarAtual();

        $this->assertSame(2, ItemDeTeste::query()->paraTodasAsLojas()->count());
        $this->assertSame(['da B'], ItemDeTeste::query()->daLoja($lojaB)->pluck('nome')->all());
    }
}

/**
 * Entidade de mentira, só para exercitar o trait.
 */
class ItemDeTeste extends Model
{
    use PertenceALoja;

    protected $table = 'itens_de_teste';

    protected $fillable = ['loja_id', 'nome'];
}
