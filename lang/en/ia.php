<?php

declare(strict_types=1);

return [
    'link' => 'AI',
    'testar' => [
        'argumentos' => 'Give a topic (:temas), a level from 1 to 10 and, optionally, --tipo=texto or --tipo=frase.',
        'falhou' => 'Generation failed: :motivo',
        'tempo' => 'Generated in :segundos s. Nothing was saved.',
    ],
    'processar' => [
        'nada' => 'No request waiting.',
        'ocupado' => 'Another run is in progress.',
        'aguardando' => 'The AI gateway is busy or down. The request stays in the queue.',
        'processado' => 'Request processed.',
    ],
    'motivo' => [
        'aguardando' => 'Waiting for the AI gateway.',
        'invalida' => 'The model did not answer in the requested format.',
        'truncada' => 'The model answer was cut by the token limit.',
        'recusado' => 'The gateway refused the request (:status, :codigo). Check the configuration.',
    ],
    'status' => [
        'pendente' => 'Queued',
        'gerando' => 'Generating',
        'pronto' => 'Ready',
        'reprovado' => 'Rejected',
        'falhou' => 'Failed',
        'aprovado' => 'Approved',
        'descartado' => 'Discarded',
    ],
    'painel' => [
        'titulo' => 'AI drafts',
        'ajuda' => 'Pick topic, level and type. The request joins the queue, and the scheduler generates one item per minute with the local model, with up to :max requests waiting. Nothing reaches practice without your approval: approving copies the item to My texts.',
        'novo' => 'New request',
        'tipo' => 'Type',
        'gerar' => 'Generate',
        'desligada' => 'AI is off on this server: the gateway is not configured.',
        'pedido_criado' => 'Request queued. Generation starts in the next minute.',
        'limite' => 'There are already :max requests waiting. Wait for one to finish.',
        'rascunhos' => 'Drafts',
        'vazio' => 'No drafts yet.',
        'duracao' => ':segundos s',
        'aprovar' => 'Approve',
        'descartar' => 'Discard',
        'aprovado' => 'Approved: the item is in My texts.',
        'descartado' => 'Draft discarded.',
        'nao_aprovavel' => 'This draft can no longer be approved.',
        'nao_descartavel' => 'This draft can no longer be discarded.',
        'idiomas' => 'The active package needs languages this draft does not have.',
        'validacao' => [
            'tema' => 'Pick a topic from the list.',
            'nivel' => 'Pick a level from 1 to 10.',
            'tipo' => 'Pick text or sentence.',
        ],
    ],
];
