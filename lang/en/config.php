<?php

declare(strict_types=1);

return [
    'titulo' => 'Settings',
    'idiomas' => [
        'titulo' => 'Languages',
        'nativa' => 'Native language',
        'nativa_ajuda' => 'Sets the language of the interface and of the translation.',
        'treino' => 'Language to practice',
        'treino_ajuda' => 'The language of the texts and phrases.',
        'erro_mesma' => 'Choose different languages. The native language and the language to practice cannot be the same.',
    ],
    'aparencia' => [
        'titulo' => 'Appearance',
        'claro' => 'Light',
        'escuro' => 'Dark',
        'sistema' => 'System',
        'ajuda' => 'Applies to every screen. System follows the light or dark mode of your computer. The choice is kept only in this browser.',
    ],
    'traducao' => [
        'titulo' => 'Automatic translation',
        'chrome_titulo' => 'Chrome only',
        'chrome_texto' => 'Translation done by the browser itself. Free, Chrome only.',
        'google_titulo' => 'Google API',
        'google_texto' => 'Works in any browser. The text you edit is sent to Google for translation.',
        'sem_chave' => 'Key not configured on the server.',
        'ajuda' => 'Used by Swap in Reading and by Translate in the texts. The choice is kept only in this browser.',
    ],
    'textos' => [
        'titulo' => 'Texts',
        'gerenciar' => 'Manage texts',
        'ajuda' => 'Create, edit and delete your own practice texts and sentences.',
        'exige_token' => 'Requires the access token.',
        'entrar' => 'Sign in to manage',
        'desligada' => 'Management is turned off on this server.',
    ],
    'perigo' => [
        'titulo' => 'Danger zone',
        'restaurar_titulo' => 'Restore factory settings',
        'restaurar_texto' => 'Resets languages, appearance, topic, translation and level to the defaults. Affects only this browser.',
        'restaurar' => 'Restore',
        'confirmar' => 'Restore everything?',
        'apagar_titulo' => 'Delete database',
        'apagar_texto' => 'Deletes the texts you created and the imported packages. The catalog goes back to the factory package. This cannot be undone.',
        'apagar_botao' => 'Delete database…',
        'etapa' => 'Step :n of 2',
        'modal_titulo' => 'Delete database?',
        'etapa1_texto' => 'The texts and sentences you created and the imported packages will be deleted. The catalog goes back to the factory package. This cannot be undone.',
        'exportar_antes' => 'Keep a copy first:',
        'continuar' => 'Continue',
        'confirme' => 'To confirm, type :palavra:',
        'cancelar' => 'Cancel',
    ],
];
