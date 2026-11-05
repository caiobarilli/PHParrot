<?php

declare(strict_types=1);

return [
    'link' => 'IA',
    'testar' => [
        'argumentos' => 'Informe um tema (:temas), um nível de 1 a 10 e, se quiser, --tipo=texto ou --tipo=frase.',
        'falhou' => 'A geração falhou: :motivo',
        'tempo' => 'Gerado em :segundos s. Nada foi gravado.',
    ],
    'processar' => [
        'nada' => 'Nenhum pedido à espera.',
        'ocupado' => 'Outro processamento está em andamento.',
        'aguardando' => 'O gateway de IA está ocupado ou fora do ar. O pedido continua na fila.',
        'processado' => 'Pedido processado.',
    ],
    'motivo' => [
        'aguardando' => 'Aguardando o gateway de IA.',
        'invalida' => 'O modelo não respondeu no formato pedido.',
        'truncada' => 'A resposta do modelo foi cortada pelo limite de tokens.',
        'recusado' => 'O gateway recusou o pedido (:status, :codigo). Confira a configuração.',
    ],
    'status' => [
        'pendente' => 'Na fila',
        'gerando' => 'Gerando',
        'pronto' => 'Pronto',
        'reprovado' => 'Reprovado',
        'falhou' => 'Falhou',
        'aprovado' => 'Aprovado',
        'descartado' => 'Descartado',
    ],
    'painel' => [
        'titulo' => 'Rascunhos da IA',
        'ajuda' => 'Escolha tema, nível e tipo. O pedido entra na fila, e a agenda gera um item por minuto pelo modelo local, com até :max pedidos à espera. Nada vai para o treino sem a sua aprovação: aprovar copia o item para os Meus textos.',
        'novo' => 'Novo pedido',
        'tipo' => 'Tipo',
        'gerar' => 'Gerar',
        'desligada' => 'IA desligada neste servidor: o gateway não está configurado.',
        'pedido_criado' => 'Pedido na fila. A geração começa no próximo minuto.',
        'limite' => 'Já há :max pedidos à espera. Aguarde algum terminar.',
        'rascunhos' => 'Rascunhos',
        'vazio' => 'Nenhum rascunho ainda.',
        'duracao' => ':segundos s',
        'aprovar' => 'Aprovar',
        'descartar' => 'Descartar',
        'aprovado' => 'Aprovado: o item está nos Meus textos.',
        'descartado' => 'Rascunho descartado.',
        'nao_aprovavel' => 'Este rascunho não pode mais ser aprovado.',
        'nao_descartavel' => 'Este rascunho não pode mais ser descartado.',
        'idiomas' => 'O pacote ativo pede idiomas que este rascunho não tem.',
        'validacao' => [
            'tema' => 'Escolha um tema da lista.',
            'nivel' => 'Escolha um nível de 1 a 10.',
            'tipo' => 'Escolha texto ou frase.',
        ],
    ],
];
