<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Onde os temas moram
    |--------------------------------------------------------------------------
    |
    | Tema base (os que entregamos) fica em disco. A customização do lojista NÃO fica
    | aqui — vai para o banco, na coluna `lojas.configuracoes` (CLAUDE.md 7.2). O lojista
    | nunca recebe o código-fonte do tema; ele monta e testa online pelo editor.
    |
    */

    'raiz' => env('TEMA_RAIZ', storage_path('themes')),

    /*
    |--------------------------------------------------------------------------
    | Tema padrão
    |--------------------------------------------------------------------------
    |
    | Usado quando a loja não escolheu tema.
    |
    */

    'padrao' => env('TEMA_PADRAO', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Nomes de arquivo da composição
    |--------------------------------------------------------------------------
    |
    | Estrutura de um tema (CLAUDE.md 7.3):
    |
    |   layout/theme.liquid      casca: <html>, header, footer — envolve tudo
    |   templates/*.liquid       index, product, collection, cart
    |   sections/*.liquid        blocos que o lojista liga/desliga e reordena
    |   config/settings.json     tokens (cor, fonte, logo, raio de borda)
    |   config/secoes.json       ordem padrão das seções por template
    |
    */

    'layout' => 'theme',

    'pastas' => [
        'layout' => 'layout',
        'templates' => 'templates',
        'sections' => 'sections',
        'config' => 'config',
    ],
];
