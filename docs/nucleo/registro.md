# Registro técnico — Núcleo

Log honesto de decisões, problemas e correções. Escrito enquanto se constrói.

---

## Etapa 1 — Fundação

**Data:** 2026-08-13

### Objetivo
Instalar e fazer conversar: Laravel + Breeze + Filament + nwidart/laravel-modules +
stancl/tenancy + keepsuit/laravel-liquid + Scout/Meilisearch.

### Versões escolhidas

| Pacote | Versão | Observação |
|---|---|---|
| laravel/framework | 13.25.0 | Última estável no dia |
| PHP | 8.4.8 (CLI Homebrew) / 8.4 (Herd) | `nwidart/laravel-modules` v13 exige `^8.3` |
| filament/filament | 5.7.6 | Traz Livewire 4 |
| laravel/breeze | 2.4.2 (dev) | Stack Blade |
| nwidart/laravel-modules | 13.0.0 | Usa `wikimedia/composer-merge-plugin` |
| stancl/tenancy | 3.10.1 | Instalado, **inerte** no modo single |
| keepsuit/laravel-liquid | 0.6.2 | Motor de tema do storefront |
| laravel/scout | 11.5.0 | + `meilisearch/meilisearch-php` 1.17.0 |
| MySQL | 9.7.1 (DBngin) | Porta **3307** |

### Decisão: Laravel 13 e não 12
Antes de instalar, verifiquei na API do Packagist a constraint de `illuminate/*` de cada
pacote do stack. Todos já declaram suporte a `^13.0`:

- `laravel/breeze` 2.4.2 → `illuminate/support: ^11.0|^12.0|^13.0`
- `filament/support` 5.7.6 → `illuminate/contracts: ^11.28|^12.0|^13.0`
- `stancl/tenancy` 3.10.1 → `illuminate/support: ^10.0|^11.0|^12.0|^13.0`
  (a 3.9.1 ainda **não** suportava 13 — foi o pacote mais apertado)
- `keepsuit/laravel-liquid` 0.6.2 → `illuminate/contracts: ^11.0 || ^12.0 || ^13.0`
- `laravel/scout` 11.5.0 → `illuminate/support: ...|^13.0`
- `nwidart/laravel-modules` 13.0.0 → sem constraint de illuminate; exige `php ^8.3`

Conclusão: não houve necessidade de fixar Laravel 12. Se algum pacote futuro travar,
`stancl/tenancy` é o candidato a segurar a versão — ele foi o último a subir para 13.

### Decisão: banco
- Engine **DBngin "Valhalla"**, MySQL 9.7.1, **porta 3307** (a 3306 é outro engine, do projeto
  WebInclude — não mexer).
- Schemas criados: `valhalla` (dev) e `valhalla_testing` (testes).
- `phpunit.xml` aponta para `valhalla_testing` em **MySQL**, não SQLite em memória. Motivo:
  paridade com produção. Custo: suíte um pouco mais lenta. Vale, porque o projeto vai depender
  de comportamento de banco (escopo por `tenant_id`, cache, índices) e diferença de SQL
  aparecendo só no deploy é armadilha.

### Chaves de ambiente novas
```
STORE_MODE=single        # single = loja única (fábrica) | multi = SaaS
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=http://127.0.0.1:7700
```
Nos testes, `STORE_MODE=single` e `SCOUT_DRIVER=null` (sem depender de serviço externo).

### Serviços externos — estado local
- **MySQL** — rodando (DBngin, 3307). ✅
- **Meilisearch** — **não instalado** ainda. O pacote PHP está instalado, mas o servidor não.
  Só é necessário quando o módulo Busca entrar (etapa 5). Nos testes o driver é `null`.
- **Redis** — **não rodando**. Filas/cache em `database` por enquanto, como o CLAUDE.md permite
  ("Redis ou `database`"). Trocar para Redis quando o full-page cache do núcleo entrar.

### Decisão: dois painéis Filament desde já
O Filament gera um painel `admin` em `/admin`. Substituí por **dois painéis**, para os
contextos do CLAUDE.md 5.9 nascerem separados em vez de virar refatoração depois:

| Painel | id | Caminho | Contexto | Discovery |
|---|---|---|---|---|
| Lojista (padrão) | `loja` | `/painel` | dono da loja | `app/Filament/Loja/*` |
| Mestre | `master` | `/master` | super-admin (nós) | `app/Filament/Master/*` |

`loja` é o `->default()` porque é o painel do dia a dia. `AdminPanelProvider` foi removido.

### Decisão: tenancy instalado e inerte
`config/tenancy.php` → `'routes' => env('STORE_MODE', 'single') === 'multi'`. No modo loja
única o pacote não registra rota nenhuma (verificado por teste). As migrations de tenancy
**não** foram publicadas — entram na etapa v2, junto do `TenancyResolver`.

Nova chave de config: `config('app.store_mode')`, lida de `STORE_MODE`. É o único lugar que
sabe o modo; o resto do sistema vai perguntar `Loja::atual()`.

### Herd
Projeto já está em `~/Herd/Valhalla`, então o Herd serve **http://Valhalla.test**
automaticamente (site "parked", PHP 8.4). Sem configuração extra.

### Verificação (CLAUDE.md 16 — peça sem teste não está pronta)

`tests/Feature/FundacaoTest.php` — 11 testes que provam o alicerce, não regra de negócio:

- storefront `/` responde; Breeze `/login` e `/register` respondem;
- `/painel/login` e `/master/login` respondem, e os dois painéis são panels distintos
  (ids, paths e default conferidos);
- Liquid renderiza string com variável, filtro (`| plus:`) e condicional;
- **prova do sandbox:** `<?php ... ?>` dentro do template sai como texto literal, não executa;
- arquivo `.liquid` renderiza pelo view factory do Laravel (extensão registrada);
- sistema de módulos ativo e apontando para `base_path('Modules')`;
- Scout + client Meilisearch instalados (driver `null` na suíte, de propósito);
- tenancy inerte no modo single (nenhuma rota `tenancy/assets` registrada).

Resultado da suíte completa (fundação + testes do Breeze): **36 testes, 88 asserções, todos
passando** (~1,8s, contra MySQL `valhalla_testing`).

Verificado também servido pelo Herd, não só em teste: `/`, `/login`, `/register`,
`/painel/login`, `/master/login` → todos HTTP 200, e `<title>Valhalla</title>` na home.

**Correção durante a construção:** o primeiro teste de Scout afirmava
`assertNotNull(config('scout.driver'))` e falhou — o `phpunit.xml` neutraliza o driver de
propósito, então `null` é o comportamento certo. O teste estava errado, não o setup:
reescrito para checar as classes instaladas, a seção `meilisearch` da config, e que a suíte
**não** depende do servidor de busca.

### Problemas encontrados

1. **`head` inutilizável no shell.** O `head` do PATH é o `HEAD` do Perl LWP
   (`lwp-request`), não o coreutils — qualquer `| head -n 20` falha com "Unknown option: n".
   Contorno: usar `tail`, `sed -n`, sem `head`. Registrado porque atrapalha qualquer script
   de build ou CI que rode nesta máquina.
2. **`composer show <pkg> <versão>` não retornou os `require`.** Para auditar as constraints
   antes de instalar, usei a API do Packagist (`repo.packagist.org/p2/<pkg>.json`).
3. **Plugin do composer bloqueado.** `nwidart/laravel-modules` v13 depende de
   `wikimedia/composer-merge-plugin`; em `--no-interaction` o composer negaria o plugin e o
   autoload dos módulos quebraria silenciosamente. Corrigido antes de instalar com
   `composer config --no-plugins allow-plugins.wikimedia/composer-merge-plugin true`.

### Pendência aberta (vira trabalho da etapa 2)
Breeze foi instalado na fundação sobre `User` / guard `web`. Os **três contextos de auth**
(super-admin, lojista, cliente do storefront) ainda **não** estão separados — isso é núcleo
(CLAUDE.md 5.9) e é a primeira tarefa da etapa 2. Registrado para não passar batido.

---

## Etapa 2 — Núcleo

### 2.1 — Loja, resolvedor e isolamento

**Data:** 2026-08-13

#### Peças
```
app/Models/Loja.php                    model + fachada Loja::atual()
app/Nucleo/Loja/ResolvedorDeLoja.php   contrato
app/Nucleo/Loja/SingleStoreResolver.php driver de fábrica (single)
app/Nucleo/Loja/NenhumaLojaConfigurada.php
app/Nucleo/Loja/EscopoDeLoja.php       global scope por loja_id
app/Nucleo/Loja/PertenceALoja.php      trait das entidades de negócio
app/Providers/NucleoServiceProvider.php amarra o driver conforme STORE_MODE
```
Tabelas: `lojas`, `loja_modulos`.

#### Decisão: `Loja::atual()` é método estático do próprio model
O CLAUDE.md pede model `Loja` (5.1) **e** fachada `Loja::atual()` (5.2). Em vez de criar uma
Facade separada (que exigiria alias e um segundo nome para a mesma coisa), o próprio model
expõe `atual()`, delegando ao `ResolvedorDeLoja` do container. Um nome só, e o resto do
sistema nunca vê o driver.

#### Decisão: duas portas — `atual()` estoura, `atualOuNula()` não
`Loja::atual(): Loja` lança `NenhumaLojaConfigurada` quando não há loja. Gravar dado sem
loja, ou ler dado de "loja nenhuma", é vazamento de isolamento — tem que estourar alto.
Quem sabe lidar com a ausência (instalador, painel mestre) chama `Loja::atualOuNula()`.

#### Decisão: o escopo **fecha em caso de dúvida**
`EscopoDeLoja` sem loja resolvida aplica `whereRaw('1 = 0')`: devolve **vazio**, não tudo.
Vazar dado de uma loja para outra é o pior defeito possível nesta plataforma; devolver
vazio é só um bug visível. Escape hatch explícito para o painel mestre:
`->paraTodasAsLojas()` e `->daLoja($loja)`.

Na criação é o oposto — se não há loja, **estoura** em vez de gravar `loja_id` nulo.

#### Decisão: `TenancyResolver` referenciado por nome, não por import
`NucleoServiceProvider` guarda o driver multi como string
(`'App\Nucleo\Loja\TenancyResolver'`) e só resolve se `class_exists()`. Assim a build Loja
Única **não contém** o código de multi-loja (CLAUDE.md 6) e ainda dá mensagem clara se
alguém puser `STORE_MODE=multi` numa build que não tem o pacote privado.

### 2.2 — Módulos e hooks

#### Peças
```
app/Nucleo/Modulos/Modulo.php            enum nominal fechado
app/Nucleo/Modulos/RegistroDeModulos.php quem está ligado nesta loja
app/Models/LojaModulo.php
app/Nucleo/Hooks/Hooks.php               casca sobre eventos do Laravel
app/Nucleo/helpers.php                   registrar() / disparar()
```

#### Decisão: a lista de módulos é um enum, não string
CLAUDE.md 4 diz que a lista é "fechada e nominal", sem categoria vaga. String solta convida
a inventar módulo fantasma — e como plano comercial é combinação de módulos ligados (15),
nome errado é plano errado. Enum `Modulo` com os 10 módulos nominais.

#### Decisão a confirmar: `ecommerce` entrou no enum como *essencial*
O CLAUDE.md lista o Ecommerce como **módulo** (18.3), mas ele **não** aparece na lista de
liga/desliga que forma os planos (seção 4). Resolvi com um `essencial()` no enum:
`ecommerce` está sempre ligado e não é comercializável (`Modulo::comercializaveis()` o
exclui). Sem catálogo não sobra loja, então desligar não faria sentido.
**Se a intenção era outra, é só mudar `essencial()` — nada mais depende disso.**

#### Decisão: o liga/desliga é verificado no **disparo**, não no registro
Os listeners são registrados normalmente (eventos nativos do Laravel). Quando o listener é
registrado em nome de um módulo, ele é embrulhado num callback que consulta
`RegistroDeModulos::estaAtivo()` **na hora do disparo**. Consequências:

- o lojista desliga o módulo no painel e o efeito é imediato, sem reboot nem cache de
  listener;
- o mesmo listener roda numa loja e não roda na outra (o liga/desliga é por loja);
- desligar módulo **não** remove o evento: o disparo continua, sem ninguém escutando —
  exatamente o que o CLAUDE.md 5.3 pede.

`RegistroDeModulos` memoiza os módulos ativos por loja na requisição, porque isso é
consultado a cada disparo de hook.

#### Verificação
`tests/Feature/Nucleo/` — 3 arquivos:

- `LojaAtualTest` — driver de fábrica, memoização, `tornarAtual()`, `esquecer()`, exceção
  sem loja, seeder idempotente;
- `EscopoDeLojaTest` — usa uma entidade de mentira (`itens_de_teste`) de propósito: o trait
  tem que servir a qualquer entidade que um módulo venha a criar. Cobre isolamento entre
  duas lojas, preenchimento automático de `loja_id`, fail-closed sem loja, e os dois escape
  hatches;
- `ModulosEHooksTest` — lista nominal sem "outros", carrinho/checkout/cache/SEO **fora** da
  lista de módulos, liga/desliga por loja, e o **teste obrigatório**: desligar módulo
  silencia o listener e o disparo continua funcionando.

Suíte completa: **62 testes, 135 asserções, verde**.

### 2.3 — Motor de tema (ponte do Liquid)

#### Peças
```
config/tema.php                          raiz dos temas, tema padrão, nomes de pasta
app/Nucleo/Tema/Tema.php                 o que o tema TEM (templates, seções, tokens)
app/Nucleo/Tema/RepositorioDeTemas.php   descobre temas, resolve o tema da loja
app/Nucleo/Tema/ConfiguracaoDeTema.php   customização do lojista (banco)
app/Nucleo/Tema/Secao.php               seção posicionada: tipo, ativa, config
app/Nucleo/Tema/MotorDeTema.php          layout + template + sections
app/Nucleo/Tema/Drops/LojaDrop.php       primeiro drop
```

#### Como a composição funciona
As seções ativas do template são renderizadas na ordem do lojista e entregues ao template
em `content_for_sections`; o template renderizado é entregue ao layout em
`content_for_layout`. Um `index.liquid` que só tem `{{ content_for_sections }}` dá ao
lojista controle total da home só reordenando blocos, sem tocar em código (CLAUDE.md 7.5).

#### Descoberta: o keepsuit/liquid resolve template pelo view finder do Laravel
`LiquidCompiler::getPathFromTemplateName()` chama `View::getFinder()->find()`. Isso decidiu
o desenho: em vez de ler o arquivo do tema na mão e usar `parseString` (o que quebraria
`{% render %}` dentro do tema e perderia o cache de template compilado), o motor **põe o
diretório do tema na frente das view paths** durante o render.

Efeito colateral a controlar: view path é estado global. `comOTemaAtivo()` salva as paths,
troca, renderiza e **devolve as originais num `finally`** — inclusive quando dá exceção.
Sem isso, um render contaminaria o próximo (grave no SaaS e em worker de fila, onde vários
temas passam pelo mesmo processo). Tem teste para os dois caminhos, com e sem exceção.

#### Decisão: o tema declara a ordem padrão das seções em `config/secoes.json`
O CLAUDE.md 7.3 define `config/settings.json` para tokens, mas não diz onde vive a ordem
padrão das seções. Sem isso, instalação nova abriria a home vazia. Então o tema declara
`config/secoes.json` (`{"index": ["banner", "destaques"]}`) e o lojista sobrescreve pelo
painel; o que ele salva vai para `lojas.configuracoes.secoes`.

#### Decisão: seção que não existe no tema é ignorada em silêncio
Trocar de tema não pode derrubar a loja. Se o arranjo salvo cita uma seção que o tema novo
não tem, ela é filtrada — a página sai sem aquele bloco em vez de estourar.

#### Como o drop trava o acesso (regra de ouro 5)
O `Drop` do keepsuit expõe ao Liquid **apenas propriedades públicas e métodos públicos sem
argumento**. `LojaDrop` guarda o model numa propriedade `protected #[Hidden]` e publica
três métodos (`nome`, `slug`, `url`), cada um com `#[Cache]`. Teste explícito garante que
`$drop->toArray()` devolve exatamente `['nome','slug','url']` — se alguém publicar um
método por descuido, o teste quebra.

`ProdutoDrop`, `ColecaoDrop` e `ClienteDrop` **não** entram aqui: pertencem ao módulo
Ecommerce (etapa 3), que é quem declara os drops que expõe (CLAUDE.md 14). `CarrinhoDrop`
vem com o carrinho/checkout, que é núcleo e ainda não foi construído.

#### Decisão de escopo: nenhum tema de verdade foi criado
O tema default é a **etapa 4** do CLAUDE.md. O motor é testado contra dois temas de mentira
em `tests/fixtures/themes/`. `storage/themes/` está vazio de propósito — e é por isso que
a rota `/` do storefront ainda é a página padrão do Laravel, não o motor de tema.

#### Verificação
`tests/Feature/Nucleo/MotorDeTemaTest.php` — 20 testes: composição layout/template/seções,
ordem do lojista sobrepondo a do tema, seção desligada, config por seção, seção fantasma
ignorada, render de seção isolada (para AJAX), tokens com sobrescrita campo a campo, o
whitelist do drop, troca de tema, e as view paths voltando ao normal com e sem exceção.

Suíte completa: **82 testes, 167 asserções, verde**.
