<?php

namespace Tests\Feature\Nucleo;

use App\Models\Loja;
use App\Nucleo\Hooks\Hooks;
use App\Nucleo\Modulos\Modulo;
use App\Nucleo\Modulos\RegistroDeModulos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sistema de módulos + hooks (CLAUDE.md 5.3) e a regra de ouro 2: módulo nunca chama
 * módulo direto, e desligar módulo jamais pode quebrar a loja.
 */
class ModulosEHooksTest extends TestCase
{
    use RefreshDatabase;

    protected Loja $loja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loja = Loja::factory()->create();
        $this->loja->tornarAtual();
    }

    // ------------------------------------------------------------- lista nominal fechada

    public function test_a_lista_de_modulos_e_nominal_e_nao_tem_categoria_vaga(): void
    {
        $valores = array_map(fn (Modulo $m) => $m->value, Modulo::cases());

        $this->assertSame([
            'ecommerce', 'pagamento', 'frete', 'moeda', 'fiscal', 'estoque',
            'catalogo', 'busca', 'cupom', 'chat', 'idioma',
        ], $valores);

        $this->assertNotContains('outros', $valores);
        $this->assertNotContains('diversos', $valores);
    }

    public function test_carrinho_checkout_e_cache_nao_sao_modulos(): void
    {
        $valores = array_map(fn (Modulo $m) => $m->value, Modulo::cases());

        // São núcleo (CLAUDE.md 4): se fossem módulo desligável, não sobraria loja.
        foreach (['carrinho', 'checkout', 'cache', 'seo', 'midia', 'performance'] as $nucleo) {
            $this->assertNotContains($nucleo, $valores, "[{$nucleo}] é núcleo, não módulo");
        }
    }

    public function test_modulos_comercializaveis_excluem_o_essencial(): void
    {
        $comercializaveis = Modulo::comercializaveis();

        $this->assertNotContains(Modulo::Ecommerce, $comercializaveis);
        $this->assertContains(Modulo::Pagamento, $comercializaveis);
        $this->assertCount(count(Modulo::cases()) - 1, $comercializaveis);
    }

    // -------------------------------------------------------------- liga/desliga por loja

    public function test_modulo_nasce_desligado(): void
    {
        $this->assertFalse(app(RegistroDeModulos::class)->estaAtivo(Modulo::Pagamento));
    }

    public function test_ligar_e_desligar_modulo_na_loja(): void
    {
        $registro = app(RegistroDeModulos::class);

        $registro->ativar(Modulo::Pagamento);
        $this->assertTrue($registro->estaAtivo(Modulo::Pagamento));

        $registro->desativar(Modulo::Pagamento);
        $this->assertFalse($registro->estaAtivo(Modulo::Pagamento));
    }

    public function test_modulo_essencial_esta_sempre_ligado(): void
    {
        $registro = app(RegistroDeModulos::class);

        $this->assertTrue($registro->estaAtivo(Modulo::Ecommerce));
        $this->assertContains(Modulo::Ecommerce, $registro->ativos());
    }

    public function test_ligar_modulo_numa_loja_nao_liga_na_outra(): void
    {
        $outra = Loja::factory()->create();
        $registro = app(RegistroDeModulos::class);

        $registro->ativar(Modulo::Frete);

        $this->assertTrue($registro->estaAtivo(Modulo::Frete, $this->loja));
        $this->assertFalse($registro->estaAtivo(Modulo::Frete, $outra));
    }

    // --------------------------------------------------------------------------- hooks

    public function test_hook_do_nucleo_sempre_roda(): void
    {
        $tocou = false;

        registrar('pedido.pago', function () use (&$tocou) {
            $tocou = true;
        });

        disparar('pedido.pago', ['id' => 1]);

        $this->assertTrue($tocou);
    }

    public function test_hook_recebe_o_payload(): void
    {
        $recebido = null;

        registrar('pedido.pago', function ($pedido) use (&$recebido) {
            $recebido = $pedido;
        });

        disparar('pedido.pago', ['id' => 42, 'total' => 199.9]);

        $this->assertSame(['id' => 42, 'total' => 199.9], $recebido);
    }

    public function test_listener_de_modulo_ligado_roda(): void
    {
        app(RegistroDeModulos::class)->ativar(Modulo::Fiscal);

        $notasEmitidas = 0;

        registrar('pedido.pago', function () use (&$notasEmitidas) {
            $notasEmitidas++;
        }, Modulo::Fiscal);

        disparar('pedido.pago', ['id' => 1]);

        $this->assertSame(1, $notasEmitidas);
    }

    /**
     * ESTE É O TESTE OBRIGATÓRIO (CLAUDE.md 16): desligar módulo não quebra a loja.
     *
     * O evento continua sendo disparado; simplesmente não sobra ninguém escutando.
     */
    public function test_desligar_modulo_silencia_o_listener_sem_quebrar_o_disparo(): void
    {
        $registro = app(RegistroDeModulos::class);
        $registro->ativar(Modulo::Fiscal);

        $notasEmitidas = 0;

        registrar('pedido.pago', function () use (&$notasEmitidas) {
            $notasEmitidas++;
        }, Modulo::Fiscal);

        disparar('pedido.pago', ['id' => 1]);
        $this->assertSame(1, $notasEmitidas);

        $registro->desativar(Modulo::Fiscal);

        // O disparo continua funcionando — não estoura, não avisa, só não faz nada.
        $retorno = disparar('pedido.pago', ['id' => 2]);

        $this->assertSame(1, $notasEmitidas, 'listener de módulo desligado rodou');
        $this->assertIsArray($retorno);
    }

    public function test_disparar_hook_sem_ninguem_escutando_nao_estoura(): void
    {
        $this->assertSame([], disparar('hook.que.ninguem.escuta', ['x' => 1]));
        $this->assertFalse(app(Hooks::class)->temListeners('hook.que.ninguem.escuta'));
    }

    /**
     * O liga/desliga é por loja: o mesmo listener roda numa loja e não roda na outra.
     */
    public function test_o_liga_desliga_do_listener_e_por_loja(): void
    {
        $outra = Loja::factory()->create();
        app(RegistroDeModulos::class)->ativar(Modulo::Cupom, $this->loja);

        $rodou = [];

        registrar('carrinho.calculado', function () use (&$rodou) {
            $rodou[] = Loja::atual()->id;
        }, Modulo::Cupom);

        disparar('carrinho.calculado');

        $outra->tornarAtual();
        disparar('carrinho.calculado');

        $this->assertSame([$this->loja->id], $rodou);
    }
}
