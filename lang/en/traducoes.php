<?php

declare(strict_types=1);

return [
    'titulo' => 'Content translation',
    'link' => 'Translation',
    'ajuda' => 'Google translates the active pack and your texts from English, in batches of up to 25 items or 5,000 characters, every 10 minutes. Visitors never trigger a translation: the draw only uses what is already translated.',
    'cota_titulo' => 'Google quota',
    'cota' => 'The app does not limit translation. Google\'s free plan covers 500,000 characters per month, as a monthly US$10 credit shared by Basic and Advanced; above that, Basic charges US$20 per million characters, and spaces count. Set the quota and the budget alert in the Google console.',
    'contador' => 'Sent to Google in :mes: :n characters.',
    'contador_ajuda' => 'For information only: counts everything the app sent to Google this month, including Reading translations. It blocks nothing.',
    'sem_chave' => 'No key: translation paused. Translations already stored keep working.',
    'lote_agora' => 'Translate next batch now',
    'idiomas' => 'Automatic languages',
    'pausada' => 'Paused',
    'pausar' => 'Pause',
    'retomar' => 'Resume',
    'traduzir' => 'Translate',
    'na_fila' => 'Queued',
    'completo' => 'Complete',
    'cobertura' => ':feitos of :total',
    'falta' => '{0} Nothing queued.|{1} 1 item queued, :caracteres characters.|[2,*] :n items queued, :caracteres characters.',
    'exportar_com' => 'Export with :idioma',
    'exportar_ajuda' => 'Builds the active pack in format 2 with the automatic translation. Review it and import it again with the language in idiomas: the pack text then wins over the cache.',
    'sem_automaticas' => 'Every language is already reviewed in the active pack.',
    'lote' => [
        'traduzido' => 'Batch translated: :itens items in :idioma, :caracteres characters.',
        'nada' => 'Nothing queued for translation.',
        'ocupado' => 'Another batch is running. Try again in a moment.',
        'sem_chave' => 'No key: translation paused.',
        'falhou' => 'Google did not respond. The batch stays for next time.',
    ],
    'limpas' => '{0} No orphan translations.|{1} 1 orphan translation deleted.|[2,*] :n orphan translations deleted.',
    'automatico' => ':idioma (automatic)',
    'etiqueta' => 'Automatic translation',
    'testar_desligado' => 'Testing is off in this language until transcription is measured in it.',
];
