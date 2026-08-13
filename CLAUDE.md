# CLAUDE.md — Plataforma E-commerce Modular

Este arquivo é o contexto-mestre do projeto. Leia por completo antes de escrever qualquer
código. Ele define a arquitetura, as regras inegociáveis e o modo de trabalho.

---

## 1. Visão geral

Estamos construindo uma **plataforma genérica** sobre a qual todo e-commerce roda como
**módulo**. O núcleo não sabe o que é "produto" — ele só sabe isolar lojas, carregar módulos,
renderizar temas e autenticar. Toda funcionalidade de negócio é um módulo plugável.

Dois formatos comerciais saem da **mesma base de código**:
- **Loja Única** — instalável, vendável (o cliente instala a cópia dele).
- **SaaS multi-loja** — hospedado por nós, assinatura.

Regra que rege tudo: **loja única é multi-loja com N=1.** Construímos sempre multi-capaz,
mas atrás de uma abstração, de modo que rodar como loja única seja trocar uma flag.

Ordem de entrega: **v1 = loja única** funcionando de ponta a ponta; **v2 = multi-loja**.

---

## 2. Princípios inegociáveis (regras de ouro)

1. **Núcleo genérico, funcionalidade é módulo.** Se é "funcionalidade de negócio", é módulo.
2. **Módulo nunca chama módulo direto.** Comunicação só por evento/hook. Desligar um
   módulo jamais pode quebrar a loja.
3. **Ninguém acessa a tenancy direto.** Todo código pergunta `Loja::atual()`. Nunca chame o
   pacote de multi-tenancy diretamente.
4. **Blade é seu e privado; Liquid é público e vendável.** Admin e casca da app = Blade +
   Filament. Storefront (tema) = Liquid, sempre sandbox.
5. **O tema nunca toca no banco.** Ele só enxerga *drops* (objetos seguros) montados pelo PHP.
6. **Toda página nasce com SEO.** Schema, microformatos, meta tags, sitemap: gerados
   automaticamente pelo motor de tema. O lojista nunca escreve isso.
7. **Peça sem teste não está pronta.** Construir e testar no mesmo fôlego.
8. **Módulo concluído gera documentação** (registro técnico + tutorial) em `docs/`.

---

## 3. Stack técnica

| Camada | Ferramenta | Papel |
|---|---|---|
| Framework | Laravel | Alicerce (rotas, models, filas) |
| Auth storefront | Laravel Breeze | Login/registro do cliente da loja |
| Painel admin | Filament (Livewire) | Painel do super-admin e do lojista; reativo de fábrica |
| Módulos | nwidart/laravel-modules | Cada funcionalidade num pacote isolado |
| Multi-tenancy | stancl/tenancy | Isolamento por loja (só na edição SaaS) |
| Motor de tema | keepsuit/laravel-liquid | Renderiza os temas `.liquid` do storefront |
| Busca | Laravel Scout + driver Meilisearch | Busca de produtos |
| Storefront reativo | AJAX + HTMX/Alpine | Interações sem reload, preservando HTML server-side |
| Banco | MySQL | Persistência |
| Filas | Redis (ou `database`) | E-mail, webhooks, indexação, IndexNow |

---

## 4. Arquitetura em camadas

```
Storefront (.liquid)            → o que o cliente vê; sandbox, vendável
        ▲
Camada de tema (Liquid)         → motor + drops + seções/blocos + tokens + SEO
        ▲
Módulos                         → pagamento, frete, moeda, fiscal, estoque, busca, ...
        ▲
Núcleo (plataforma)             → resolvedor de loja, módulos+hooks, tema, auth,
                                  painel, checkout, cache, mídia, SEO, performance
        ▲
Multi-tenancy (banco)           → um banco, cada linha com tenant_id
```

### O que é NÚCLEO (nunca é módulo)
Resolvedor de loja · sistema de módulos + hooks · motor de tema · autenticação base ·
painel base · model `Loja` · **carrinho e checkout** · **cache** · **camada de SEO** ·
**performance/Core Web Vitals** · **banco de mídia central**.

Motivo de carrinho/checkout serem núcleo: são centrais demais — se fossem módulo
desligável, não sobraria loja.

### O que é MÓDULO (liga/desliga por loja → forma os planos)
Pagamento · Frete · Moeda · Fiscal/ERP · Estoque e Variações · Catálogo multi-loja ·
Busca · Cupom/Desconto · Chat online · Multi-idioma.

> Lista **fechada e nominal**. Nunca use categoria vaga tipo "outros". Módulo novo entra
> com nome próprio.

---

## 5. Núcleo — detalhamento

### 5.1. Model `Loja`
Representa uma loja/tenant. Fonte da verdade de `tenant_id`. Toda entidade de negócio
(produto, pedido, cliente) pertence a uma `Loja`.

### 5.2. `ResolvedorDeLoja` (contrato) + drivers
Contrato único no núcleo que responde "qual é a loja atual". Dois drivers:
- **`SingleStoreResolver`** (embutido, padrão de fábrica) — devolve sempre a única loja.
- **`TenancyResolver`** (pacote privado, só na edição SaaS) — resolve pelo domínio via
  stancl/tenancy.

Chave `.env`: `STORE_MODE=single | multi`. Fachada de acesso: `Loja::atual()`.
Todo o resto do sistema usa só `Loja::atual()` e nunca sabe qual driver está ativo.

### 5.3. Sistema de módulos + hooks
- Base: nwidart/laravel-modules (cada módulo com service provider, rotas, migrations, views).
- Hooks: **eventos nativos do Laravel** por baixo, com uma casca simples estilo WordPress por
  cima — ex.: `registrar('pedido.pago', callback)` e `disparar('pedido.pago', $pedido)`.
- Registro de módulos ativos por loja (tabela `loja_modulos`): o lojista liga/desliga pelo painel.
- Desligar módulo remove os listeners dele; eventos continuam disparando, sem ninguém
  escutando — a loja não quebra.

### 5.4. Cache (desde já)
- **Full-page cache** do storefront: a página montada é guardada e servida pronta.
- Re-renderiza só quando o dado muda (invalidação por evento — ex.: `produto.atualizado`
  invalida as páginas daquele produto).
- Sem cache, cada visita re-renderiza Liquid + bate no banco → trava. Cache é obrigatório.

### 5.5. Camada de SEO (ver seção 8)
### 5.6. Camada de performance / Core Web Vitals (ver seção 8)
### 5.7. Banco de mídia central (ver seção 9)
### 5.8. Checkout (ver seção 10)

### 5.9. Autenticação — três contextos separados
1. **Super-admin** (nós) — painel Filament mestre.
2. **Lojista** (dono da loja) — painel Filament da loja.
3. **Cliente** (quem compra) — auth do storefront (Breeze).

Nunca misturar os três. Separar desde o início.

---

## 6. Loja única × multi-loja

- **Banco:** um banco só; cada linha marcada com `tenant_id`. Isolamento por padrão — uma
  loja nunca enxerga o dado da outra.
- **Painel mestre (multi):** lista as lojas do dono → ao entrar numa loja, ela vira a
  `Loja::atual()` e cai-se no painel daquela loja (ligar/desligar módulo, tema, etc.).
- **Comercialização:** duas builds da mesma base — Loja Única (sem o pacote de tenancy, flag
  travada em `single`) e SaaS (com o pacote privado, flag `multi`). O comprador da Loja Única
  **não recebe** o código de multi-loja.

---

## 7. Motor de tema (Liquid)

### 7.1. Por que Liquid
Sandbox seguro: o tema roda tags/filtros/variáveis, mas **não** roda PHP, não acessa banco,
arquivo ou env. Permite que lojista/designer/terceiro escreva tema sem risco e sem ver o
código — pré-requisito pra vender tema num sistema multi-loja.

### 7.2. Onde o tema mora
- **Tema base** (os que entregamos): Git privado + servidor.
- **Customização do lojista:** salva no banco. Ele nunca recebe o código-fonte; monta e
  **testa tudo online** pelo editor.

### 7.3. Composição de um tema
```
storage/themes/{tema}/
  layout/theme.liquid        # casca: <html>, header, footer — envolve tudo
  templates/
    index.liquid             # home
    product.liquid           # página de produto
    collection.liquid        # categoria/coleção
    cart.liquid              # carrinho
  sections/                  # blocos ligáveis/desligáveis/reordenáveis pelo lojista
  config/settings.json       # tokens de tema (cor, fonte, logo, raio de borda)
```
O motor injeta o `template` dentro do `layout` e resolve as `sections` na ordem que o lojista
configurou pelo painel.

### 7.4. Drops (objetos seguros)
O controller **nunca** entrega o Model Eloquent cru ao tema. Embrulha num *drop* que expõe
só os campos liberados. Ex.: `ProdutoDrop` expõe `produto.nome`, `produto.preco`,
`produto.imagem`, `produto.disponivel` — e nada além. Toda lógica pesada (buscar, filtrar,
calcular) fica no PHP; o tema só exibe (`{{ }}`), decide (`if`) e repete (`for`).

Drops a expor no v1 (Ecommerce): `produto`, `colecao`, `carrinho`, `loja`, `cliente`.

### 7.5. Como o lojista customiza
- Mexe só nos **tokens** (cor, fonte, logo) e liga/desliga/reordena **seções**.
- Nunca edita código. Mexer no código do tema é caso avançado (nós/designer).

### 7.6. Fluxo de renderização
```
requisição → Loja::atual() → controller busca dados (escopo da loja) →
monta os drops → motor Liquid junta layout + template + sections →
HTML (servido do cache quando possível)
```

---

## 8. SEO e performance (camada padrão do tema — automática)

Todo o arsenal abaixo é **gerado automaticamente por página** pelo motor de tema. O lojista
nunca escreve nada disso. Padrão adaptado de e-commerce (usar `Product`, não
`SoftwareApplication`).

### 8.1. Dados estruturados (JSON-LD) por tipo de página
- Produto → `Product` + `Offer` + `AggregateRating`
- Navegação → `BreadcrumbList`
- FAQ → `FAQPage`
- Rodapé/loja → `Organization`

### 8.2. Microformatos e semântica HTML5
`h-entry`, `h-card`, `itemscope/itemprop`, `<dl>/<dt>/<dd>`, `<details>/<summary>`,
`<mark>`, `<code>/<pre>` nos templates base.

### 8.3. Meta tags (por página)
`<title>`, `description`, `canonical`, `robots` (`index, follow, max-image-preview:large,
max-snippet:-1`), Open Graph (`og:*`), Twitter Cards (`summary_large_image`).

### 8.4. Indexação e feeds
- **Sitemap** automático, alimentado ao criar/editar produto e página.
- **IndexNow** — ping instantâneo (Bing/Yandex) ao criar/editar/remover produto.
- **RSS/feed** de novidades.
- **URLs amigáveis** sempre.

### 8.5. Performance / Core Web Vitals (núcleo)
- Imagens WebP/AVIF com `srcset` e `<picture>`; `loading="lazy"` fora da dobra.
- `fetchpriority` (high para LCP, low para o secundário); `width/height` sempre (evita CLS).
- CSS crítico inline + resto assíncrono; minificação/compressão (Gzip/Brotli).
- Fontes: `font-display: swap` + preload das críticas; subsetting.
- Desejável quando o servidor permitir: `103 Early Hints`, Speculation Rules (prerender/prefetch).

---

## 9. Banco de mídia central

- A imagem existe **uma vez só** num repositório central; produtos apenas **apontam** pra ela.
- Nada de upload duplicado: quando a loja Y usa um produto da loja X, herda o mesmo
  apontamento — zero cópia no disco.
- **Reference counting:** cada uso soma 1; trocar/remover subtrai 1; ao chegar a 0 o arquivo é
  apagado automaticamente (um job de limpeza cuida disso).
- Enquanto qualquer loja apontar pra imagem, o arquivo permanece.
- Efeito bônus: trocar a foto no dono atualiza em todas as lojas que a usam.

---

## 10. Checkout (núcleo)

**Checkout rápido:** pede só o mínimo pra fechar; o resto vem depois do pagamento. Reduz
abandono de carrinho.

Etapas:
1. **Carrinho** — jogo rápido, forçando o fechamento.
2. **Identificação mínima** — nome, CPF, telefone.
3. **Endereço de entrega.**
4. **Pagamento.**
5. **Confirmação.**
6. **Pós-pagamento** — cliente completa os demais dados (o que faltar para nota etc.).

Todas as transições acontecem **sem recarregar a página** (ver seção 11).

---

## 11. Reatividade (sem reload)

- **Admin (Filament/Livewire):** reativo total de fábrica — salvar, editar, avançar sem F5.
  Não requer trabalho extra.
- **Storefront (Liquid):** a página **carrega renderizada no servidor** (essencial para SEO e
  cache); as **interações** — adicionar ao carrinho, avançar checkout, atualizar quantidade,
  aplicar filtros — acontecem **sem reload**, via AJAX atualizando só o trecho da tela
  (HTMX/Alpine). Nunca virar SPA puro no storefront: isso quebraria o SEO server-side.

---

## 12. Busca (módulo, Meilisearch de cara)

- Laravel Scout com driver **Meilisearch** desde o v1 (não usar busca simples de banco).
- Tolera erro de digitação, ordena por relevância, filtra rápido, resultado instantâneo.
- Reindexação disparada por evento (`produto.criado/atualizado/removido`).

---

## 13. Módulos — detalhamento

Cada módulo liga/desliga por loja e compõe os planos comerciais.

### 13.1. Pagamento
Abstrai vários gateways por contrato; o lojista liga os que quiser. Drivers:
Pix direto · Pagar.me · Mercado Pago · Asaas · PagSeguro · Stripe (dólar) · PayPal (dólar).
Recebe webhooks e dispara `pedido.pago`.

### 13.2. Frete
Drivers: Correios · Melhor Envio (agregador, já traz várias transportadoras) · transportadora
direta · retirada na loja. Arquitetura aberta a novas plataformas de frete. Calcula por CEP/peso.

### 13.3. Moeda
Lojista escolhe a moeda de venda (real ou dólar). Produtos exibidos e cobrados na moeda
escolhida, com conversão e formatação de valores. Casa com Stripe/PayPal para dólar.

### 13.4. Fiscal / ERP
Abstrai ERPs por contrato; comunicação **por evento** (`pedido.pago` → emitir nota),
desacoplada do checkout. Drivers: Bling · Tiny. Aberto a outros ERPs.

### 13.5. Estoque e Variações
Variação por **atributos flexíveis** — cada produto define os seus:
- Roupa → tamanho, cor
- Suplemento → sabor, peso
- Medicamento → dosagem, quantidade (**camada regulatória Anvisa** tratada depois)

Controle de estoque por variação. Nichos-foco iniciais: roupa, suplemento, medicamento.

### 13.6. Catálogo multi-loja (v2, lojas do mesmo dono)
- Produto tem **uma loja dona** (`tenant_id`).
- Vínculo "produto liberado para a loja Y", criado **só pelo dono** no painel mestre.
- **Herança + sobrescrita campo a campo:** a loja Y herda tudo do dono e sobrescreve só o que
  quiser.
- **Preço e estoque sempre próprios** de cada loja.
- Correção no dono propaga para as lojas que não sobrescreveram aquele campo.
- Usa a biblioteca de mídia central (imagem referenciada, não duplicada).

### 13.7. Cupom / Desconto
Regras de desconto (percentual, fixo, frete grátis, primeira compra). Aplicado no carrinho.

### 13.8. Chat online
Atendimento no storefront.

### 13.9. Multi-idioma (fora do v1, previsto)
Feito em **duas camadas** (vai além de traduzir texto solto). Moeda entra no v1; idioma não.

---

## 14. Contrato de módulo

Todo módulo declara:

| Declara | Descrição |
|---|---|
| `module.json` | Nome, versão, dependências de outros módulos |
| Migrations | Tabelas próprias |
| Rotas | `admin.php` (painel) + `storefront.php` (loja) |
| Recursos Filament | Telas do módulo no painel |
| Drops Liquid | Objetos que expõe ao tema (ex.: `produto`, `carrinho`) |
| Hooks | Eventos que escuta e/ou dispara |
| Seções de tema | Blocos que o lojista liga/desliga e reordena |
| Config | Configurações próprias |
| Testes | Incluindo o teste de ligar/desligar sem quebrar a loja |
| Docs | `registro.md` + `tutorial.md` em `docs/` |

---

## 15. Comercialização e planos

- **Planos = combinação de módulos ligados.** Ex.: Básico (loja + pagamento + frete) → Pro
  (+ fiscal + cupom) → Premium (+ multi-idioma + chat).
- Módulo avulso também é vendável ("quer o Bling? +R$X/mês").
- A modularidade **é** o motor comercial. Por isso a regra 2 (módulo não depende de módulo)
  é sagrada: sem ela, ligar/desligar quebra coisa.

---

## 16. Testes (regra de trabalho)

- **Constrói e testa junto.** Peça sem teste passando não está pronta.
- Testar cada parte ao concluí-la (checkout, cada gateway, cada driver de frete, etc.), nunca
  deixar pro fim.
- **Teste obrigatório de todo módulo:** ligar e desligar sem quebrar a loja.

---

## 17. Documentação por módulo (OBRIGATÓRIO)

**Toda vez que um módulo for concluído, gerar dois materiais dentro de `docs/`:**

1. **Registro técnico** (`registro.md`) — o que foi feito, como foi construído, decisões
   tomadas, problemas encontrados e como foram resolvidos. Serve para consulta futura se
   surgir bug ou dúvida de manutenção.
2. **Tutorial de uso** (`tutorial.md`) — como usar/configurar aquele módulo, passo a passo,
   do ponto de vista de quem opera.

Estrutura da pasta:
```
docs/
  modulos/
    ecommerce/
      registro.md
      tutorial.md
    pagamento/
      registro.md
      tutorial.md
    frete/
      registro.md
      tutorial.md
    ...
  nucleo/
    registro.md
    tutorial.md
```

O `registro.md` é escrito enquanto se constrói (log honesto de decisões e correções). O
`tutorial.md` é escrito ao concluir. Nenhum módulo é considerado "entregue" sem os dois.

---

## 18. Ordem de construção

1. **Fundação** — instalar Laravel + Breeze + Filament + nwidart/laravel-modules +
   stancl/tenancy + keepsuit/laravel-liquid + Scout/Meilisearch; tudo conversando.
2. **Núcleo** — model `Loja`; `ResolvedorDeLoja` + `SingleStoreResolver`; registro de
   módulos + hooks; ponte do Liquid (renderizar `.liquid` com drops); cache; camada de SEO;
   performance; banco de mídia central; carrinho e checkout. + `docs/nucleo/`.
3. **Módulo Ecommerce** — produtos, coleções, carrinho, pedidos; expõe os drops. + docs.
4. **Tema default `.liquid`** — consome o Ecommerce; primeira loja renderizando com SEO.
5. **Demais módulos** — pagamento, frete, moeda, fiscal, estoque/variações, busca, cupom,
   chat. Cada um com testes + docs.
6. **(v2) Multi-loja** — ligar `TenancyResolver` (`STORE_MODE=multi`) + catálogo multi-loja.
   Sem tocar em módulo nenhum.

---

## 19. Decisões ainda em aberto (não travam a construção)

- Licença da build vendável (chave de licença? ionCube? servidor de updates?).
- Deploy / CI (GitHub Actions) e como gerar as duas builds (Loja Única × SaaS).

Resolver na hora certa (ao empacotar para venda / publicar). Não segurar o núcleo por isso.
