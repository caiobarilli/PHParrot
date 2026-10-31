<?php

declare(strict_types=1);

return [
    'titulo' => 'Configuração',
    'idiomas' => [
        'titulo' => 'Idiomas',
        'nativa' => 'Língua nativa',
        'nativa_ajuda' => 'Define o idioma da interface e da tradução.',
        'treino' => 'Língua para treinar',
        'treino_ajuda' => 'A língua dos textos e das frases.',
        'erro_mesma' => 'Escolha línguas diferentes. A língua nativa e a língua para treinar não podem ser a mesma.',
    ],
    'aparencia' => [
        'titulo' => 'Aparência',
        'claro' => 'Claro',
        'escuro' => 'Escuro',
        'sistema' => 'Sistema',
        'ajuda' => 'Vale para todas as telas. Sistema segue o modo claro ou escuro do seu computador. A escolha fica guardada só neste navegador.',
    ],
    'traducao' => [
        'titulo' => 'Tradução automática',
        'chrome_titulo' => 'Apenas Chrome',
        'chrome_texto' => 'Tradução feita pelo próprio navegador. Sem custo, só no Chrome.',
        'google_titulo' => 'Google API',
        'google_texto' => 'Funciona em qualquer navegador. O texto que você editar é enviado ao Google para tradução.',
        'sem_chave' => 'Chave não configurada no servidor.',
        'ajuda' => 'Usada no Inverter da Leitura e no Traduzir dos textos. A escolha fica guardada só neste navegador.',
    ],
    'textos' => [
        'titulo' => 'Textos',
        'gerenciar' => 'Gerenciar textos',
        'ajuda' => 'Crie, edite e apague os seus textos e frases de treino.',
        'exige_token' => 'Exige o token de acesso.',
        'entrar' => 'Entrar para gerenciar',
        'desligada' => 'Gestão desligada neste servidor.',
    ],
    'perigo' => [
        'titulo' => 'Zona de perigo',
        'restaurar_titulo' => 'Restaurar configurações de fábrica',
        'restaurar_texto' => 'Volta idiomas, aparência, tema, tradução e nível ao padrão. Mexe só neste navegador.',
        'restaurar' => 'Restaurar',
        'confirmar' => 'Restaurar tudo?',
        'apagar_titulo' => 'Apagar banco',
        'apagar_texto' => 'Apaga os textos que você criou e os pacotes importados. O catálogo volta para a fábrica. Não dá para desfazer.',
        'apagar_botao' => 'Apagar banco…',
        'etapa' => 'Etapa :n de 2',
        'modal_titulo' => 'Apagar banco?',
        'etapa1_texto' => 'Os textos e frases que você criou e os pacotes importados serão apagados. O catálogo volta para a fábrica. Não dá para desfazer.',
        'exportar_antes' => 'Guarde uma cópia antes:',
        'continuar' => 'Continuar',
        'confirme' => 'Para confirmar, digite :palavra:',
        'cancelar' => 'Cancelar',
    ],
];
