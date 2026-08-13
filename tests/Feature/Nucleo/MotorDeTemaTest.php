<?php

namespace Tests\Feature\Nucleo;

use App\Models\Loja;
use App\Nucleo\Tema\ConfiguracaoDeTema;
use App\Nucleo\Tema\Drops\LojaDrop;
use App\Nucleo\Tema\MotorDeTema;
use App\Nucleo\Tema\RepositorioDeTemas;
use App\Nucleo\Tema\Secao;
use App\Nucleo\Tema\TemaNaoEncontrado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Motor de tema (CLAUDE.md 7): layout + template + sections, tokens, drops e sandbox.
 *
 * Roda contra os temas de mentira em tests/fixtures/themes — o tema default de verdade é
 * a etapa 4.
 */
class MotorDeTemaTest extends TestCase
{
    use RefreshDatabase;

    protected Loja $loja;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tema.raiz', base_path('tests/fixtures/themes'));
        config()->set('tema.padrao', 'teste');

        app(RepositorioDeTemas::class)->esquecer();

        $this->loja = Loja::factory()->create(['nome' => 'Loja Valhalla']);
        $this->loja->tornarAtual();
    }

    protected function motor(): MotorDeTema
    {
        return app(MotorDeTema::class);
    }

    // ------------------------------------------------------------------- composição

    public function test_o_layout_envolve_o_template(): void
    {
        $html = $this->motor()->renderizar('vazio');

        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringContainsString('<title>Loja Valhalla</title>', $html);
        $this->assertStringContainsString('<main>nada aqui</main>', $html);
    }

    public function test_o_template_atual_chega_ao_tema(): void
    {
        $this->assertStringContainsString('data-template="vazio"', $this->motor()->renderizar('vazio'));
    }

    public function test_as_secoes_saem_na_ordem_padrao_do_tema(): void
    {
        $html = $this->motor()->renderizar('index');

        $this->assertStringContainsString('class="banner"', $html);
        $this->assertStringContainsString('class="destaques"', $html);
        $this->assertLessThan(
            strpos($html, 'class="destaques"'),
            strpos($html, 'class="banner"'),
            'banner deveria vir antes de destaques'
        );
    }

    public function test_o_lojista_pode_reordenar_as_secoes(): void
    {
        app(ConfiguracaoDeTema::class)->salvarSecoes('index', [
            new Secao('destaques'),
            new Secao('banner'),
        ]);

        $html = $this->motor()->renderizar('index');

        $this->assertLessThan(
            strpos($html, 'class="banner"'),
            strpos($html, 'class="destaques"'),
            'a ordem do lojista deveria valer sobre a do tema'
        );
    }

    public function test_secao_desligada_nao_aparece(): void
    {
        app(ConfiguracaoDeTema::class)->salvarSecoes('index', [
            new Secao('banner', ativa: false),
            new Secao('destaques'),
        ]);

        $html = $this->motor()->renderizar('index');

        $this->assertStringNotContainsString('class="banner"', $html);
        $this->assertStringContainsString('class="destaques"', $html);
    }

    public function test_a_secao_recebe_a_propria_configuracao(): void
    {
        app(ConfiguracaoDeTema::class)->salvarSecoes('index', [
            new Secao('banner', configuracoes: ['titulo' => 'Frete grátis']),
        ]);

        $this->assertStringContainsString('Frete grátis', $this->motor()->renderizar('index'));
    }

    /**
     * Trocar de tema não pode derrubar a loja: seção que o tema novo não tem é ignorada.
     */
    public function test_secao_inexistente_no_tema_e_ignorada_sem_quebrar(): void
    {
        app(ConfiguracaoDeTema::class)->salvarSecoes('index', [
            new Secao('secao-que-nao-existe'),
            new Secao('destaques'),
        ]);

        $html = $this->motor()->renderizar('index');

        $this->assertStringContainsString('class="destaques"', $html);
    }

    /**
     * Interação sem reload (CLAUDE.md 11): troca só um trecho da tela, sem layout.
     */
    public function test_renderiza_secao_isolada_para_ajax(): void
    {
        $html = $this->motor()->renderizarSecao('destaques');

        $this->assertStringContainsString('destaques de Loja Valhalla', $html);
        $this->assertStringNotContainsString('<!doctype html>', $html);
    }

    public function test_renderizar_secao_inexistente_devolve_vazio(): void
    {
        $this->assertSame('', $this->motor()->renderizarSecao('nao-existe'));
    }

    // ----------------------------------------------------------------------- tokens

    public function test_tokens_padrao_do_tema_chegam_ao_template(): void
    {
        $this->assertStringContainsString('data-cor="azul"', $this->motor()->renderizar('vazio'));
    }

    public function test_o_token_do_lojista_sobrescreve_o_do_tema(): void
    {
        app(ConfiguracaoDeTema::class)->salvarTokens(['cor_primaria' => 'vermelho']);

        $html = $this->motor()->renderizar('vazio');

        $this->assertStringContainsString('data-cor="vermelho"', $html);

        // Sobrescrita é campo a campo: o que ele não mexeu continua vindo do tema.
        $this->assertSame('Inter', app(ConfiguracaoDeTema::class)->tokens()['fonte']);
    }

    // ------------------------------------------------------------------------ drops

    /**
     * Regra de ouro 5: o tema só enxerga drops. O drop expõe campo por campo.
     */
    public function test_o_drop_expoe_so_o_que_foi_liberado(): void
    {
        $html = $this->motor()->renderizar('vazio');

        $this->assertStringContainsString('Loja Valhalla', $html);

        // `configuracoes` existe no model e NÃO no drop: o tema não alcança.
        $this->assertStringNotContainsString('cor_primaria":', $html);
    }

    public function test_o_tema_nao_alcanca_o_model_por_tras_do_drop(): void
    {
        $drop = new LojaDrop($this->loja);

        $expostos = array_keys($drop->toArray());

        $this->assertSame(['nome', 'slug', 'url'], $expostos);
        $this->assertNotContains('loja', $expostos, 'o model Eloquent vazou para o tema');
        $this->assertNotContains('configuracoes', $expostos);
    }

    // ------------------------------------------------------------------ troca de tema

    public function test_a_loja_escolhe_o_tema(): void
    {
        app(ConfiguracaoDeTema::class)->escolherTema('outro');

        $html = $this->motor()->renderizar('index');

        $this->assertStringContainsString('outro-tema', $html);
        $this->assertStringNotContainsString('<!doctype html>', $html);
    }

    public function test_escolher_tema_inexistente_estoura_antes_de_gravar(): void
    {
        try {
            app(ConfiguracaoDeTema::class)->escolherTema('fantasma');
            $this->fail('deveria ter estourado');
        } catch (TemaNaoEncontrado) {
            // não gravou nada
            $this->assertArrayNotHasKey('tema', $this->loja->fresh()->configuracoes ?? []);
        }
    }

    public function test_template_que_o_tema_nao_tem_estoura(): void
    {
        $this->expectException(TemaNaoEncontrado::class);

        $this->motor()->renderizar('template-que-nao-existe');
    }

    // ------------------------------------------------------------- não vazar estado

    /**
     * O motor põe o diretório do tema na frente das view paths para renderizar, e tem que
     * devolver as paths originais no fim — senão um render contamina o próximo (SaaS, fila).
     */
    public function test_as_view_paths_voltam_ao_normal_depois_do_render(): void
    {
        $antes = View::getFinder()->getPaths();

        $this->motor()->renderizar('vazio');

        $this->assertSame($antes, View::getFinder()->getPaths());
    }

    public function test_as_view_paths_voltam_ao_normal_mesmo_com_excecao(): void
    {
        $antes = View::getFinder()->getPaths();

        try {
            $this->motor()->renderizar('template-que-nao-existe');
        } catch (TemaNaoEncontrado) {
            // esperado
        }

        $this->assertSame($antes, View::getFinder()->getPaths());
    }

    // -------------------------------------------------------------------- descoberta

    public function test_descobre_os_temas_instalados(): void
    {
        $nomes = array_map(fn ($tema) => $tema->nome(), app(RepositorioDeTemas::class)->todos());

        sort($nomes);

        $this->assertSame(['outro', 'teste'], $nomes);
    }

    public function test_lista_templates_e_secoes_do_tema(): void
    {
        $tema = app(RepositorioDeTemas::class)->encontrar('teste');

        $this->assertSame(['index', 'product', 'vazio'], $tema->templates());
        $this->assertSame(['banner', 'destaques'], $tema->secoesDisponiveis());
    }
}
