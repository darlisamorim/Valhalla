<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Keepsuit\LaravelLiquid\Liquid;
use Laravel\Scout\EngineManager;
use Meilisearch\Client;
use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

/**
 * Fundação (CLAUDE.md 18.1) — prova que as peças do stack estão instaladas e conversando.
 *
 * Este teste não valida regra de negócio: valida o alicerce. Se ele quebra, é porque um
 * pacote saiu do lugar (versão, provider, config), e nada acima disso é confiável.
 */
class FundacaoTest extends TestCase
{
    // ---------------------------------------------------------------- storefront + auth

    public function test_storefront_responde(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_auth_do_cliente_via_breeze_responde(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    // ------------------------------------------------------------------ painéis Filament

    public function test_painel_do_lojista_responde(): void
    {
        $this->get('/painel/login')->assertOk();
    }

    public function test_painel_mestre_responde(): void
    {
        $this->get('/master/login')->assertOk();
    }

    /**
     * Os três contextos de auth são separados (CLAUDE.md 5.9). Aqui garantimos ao menos que
     * cada um tem porta de entrada própria e que os painéis são panels distintos do Filament.
     */
    public function test_os_paineis_sao_contextos_distintos(): void
    {
        $ids = collect(filament()->getPanels())->keys();

        $this->assertTrue($ids->contains('loja'), 'painel do lojista não registrado');
        $this->assertTrue($ids->contains('master'), 'painel mestre não registrado');

        $this->assertSame('loja', filament()->getDefaultPanel()->getId());
        $this->assertSame('painel', filament()->getPanel('loja')->getPath());
        $this->assertSame('master', filament()->getPanel('master')->getPath());
    }

    // ------------------------------------------------------------------- motor de tema

    /**
     * O tema é sandbox: roda tags, filtros e variáveis do Liquid — e nada além (CLAUDE.md 7.1).
     */
    public function test_liquid_renderiza_string_com_variaveis_filtros_e_condicionais(): void
    {
        $liquid = app(Liquid::class);

        $saida = (string) $liquid->environment()
            ->parseString('{{ produto.nome }} custa {{ preco | plus: 10 }}{% if disponivel %} (disponível){% endif %}')
            ->render($liquid->environment()->newRenderContext(data: [
                'produto' => ['nome' => 'Camiseta'],
                'preco' => 90,
                'disponivel' => true,
            ]));

        $this->assertSame('Camiseta custa 100 (disponível)', $saida);
    }

    /**
     * Prova do sandbox: PHP dentro do template NÃO é executado — sai como texto literal.
     * É isso que permite vender tema e deixar terceiro editar sem risco.
     */
    public function test_liquid_nao_executa_php(): void
    {
        $liquid = app(Liquid::class);

        $saida = (string) $liquid->environment()
            ->parseString('<?php echo "estourou"; ?>{{ nome }}')
            ->render($liquid->environment()->newRenderContext(data: ['nome' => 'ok']));

        $this->assertStringContainsString('<?php echo "estourou"; ?>', $saida);
        $this->assertStringNotContainsString('estourou;', str_replace('<?php echo "estourou"; ?>', '', $saida));
        $this->assertStringEndsWith('ok', $saida);
    }

    /**
     * A extensão .liquid está registrada no view finder do Laravel — ou seja, dá para
     * renderizar arquivo de tema pelo caminho normal de views.
     */
    public function test_arquivo_liquid_renderiza_pelo_view_factory(): void
    {
        View::addLocation(base_path('tests/fixtures/views'));

        $saida = view('fundacao-smoke', [
            'loja' => ['nome' => 'Valhalla', 'ativa' => true],
        ])->render();

        $this->assertStringContainsString('loja: Valhalla', $saida);
        $this->assertStringContainsString('soma: 8', $saida);
        $this->assertStringContainsString('ativa', $saida);
    }

    // ------------------------------------------------------------------ sistema de módulos

    public function test_sistema_de_modulos_esta_ativo_e_aponta_para_a_pasta_modules(): void
    {
        $this->assertSame(base_path('Modules'), config('modules.paths.modules'));
        $this->assertIsArray(Module::all());
    }

    // ---------------------------------------------------------------------------- busca

    /**
     * Scout + driver Meilisearch instalados desde o v1 (CLAUDE.md 12) — nada de busca
     * simples de banco. Aqui não subimos o servidor do Meilisearch: nos testes o driver é
     * `null` de propósito, para a suíte não depender de serviço externo.
     */
    public function test_scout_com_driver_meilisearch_esta_instalado(): void
    {
        $this->assertTrue(class_exists(EngineManager::class), 'Scout não instalado');
        $this->assertTrue(class_exists(Client::class), 'driver Meilisearch não instalado');

        $this->assertArrayHasKey('meilisearch', config('scout'));
        $this->assertNotEmpty(config('scout.meilisearch.host'));

        // Na suíte o driver é neutralizado (phpunit.xml), não configurado por engano.
        $this->assertNull(config('scout.driver'), 'a suíte não deve depender do Meilisearch');
    }

    // ------------------------------------------------------------------------- tenancy

    /**
     * Loja única é multi-loja com N=1: o pacote de tenancy está instalado, mas no modo
     * single ele fica INERTE — nenhuma rota dele registrada (CLAUDE.md 6).
     */
    public function test_tenancy_fica_inerte_no_modo_loja_unica(): void
    {
        $this->assertSame('single', config('app.store_mode'));
        $this->assertFalse(config('tenancy.routes'));

        $rotas = collect(app('router')->getRoutes())->map(fn ($rota) => $rota->uri());

        $this->assertFalse(
            $rotas->contains(fn (string $uri) => str_contains($uri, 'tenancy/assets')),
            'rota de tenancy registrada no modo loja única'
        );
    }
}
