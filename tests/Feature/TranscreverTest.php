<?php

declare(strict_types=1);

use App\Textos\Idioma;
use App\Transcricao\TranscricaoIndisponivel;
use App\Transcricao\Transcritor;
use App\Transcricao\TranscritorWhisperServer;
use Illuminate\Http\Client\Request as PedidoHttp;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\TranscritorDeTeste;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

function audioDeTeste(int $kb = 100, string $tipo = 'audio/webm', string $nome = 'fala.webm'): UploadedFile
{
    return UploadedFile::fake()->create($nome, $kb, $tipo);
}

/**
 * @param  array<string, mixed>  $extra
 * @return TestResponse<Response>
 */
function enviarAudio(array $extra = []): TestResponse
{
    return post('/transcrever', ['audio' => audioDeTeste(), 'idioma' => 'en', ...$extra], ['Accept' => 'application/json']);
}

function usarTranscritor(Transcritor $transcritor): void
{
    app()->instance(Transcritor::class, $transcritor);
}

beforeEach(function (): void {
    config([
        'phparrot.transcricao.por_minuto' => 10,
        'phparrot.transcricao.por_dia' => 200,
        'phparrot.transcricao.espera_pela_trava' => 0,
    ]);
});

it('transcreve e devolve só o texto, com o idioma pedido', function (): void {
    $fake = new TranscritorDeTeste('Good morning');
    usarTranscritor($fake);

    enviarAudio()->assertOk()->assertExactJson(['texto' => 'Good morning']);

    expect($fake->idioma)->toBe(Idioma::En)
        ->and($fake->existiaAoChamar)->toBeTrue();
});

it('apaga o temporário depois de transcrever', function (): void {
    $fake = new TranscritorDeTeste;
    usarTranscritor($fake);

    enviarAudio()->assertOk();

    expect($fake->caminho)->not->toBeNull()
        ->and(file_exists((string) $fake->caminho))->toBeFalse();
});

it('apaga o temporário e responde 503 quando o motor falha', function (): void {
    $fake = new TranscritorDeTeste(TranscricaoIndisponivel::falhou());
    usarTranscritor($fake);

    enviarAudio()->assertStatus(503)->assertJson(['message' => 'A transcrição está indisponível agora. Tente de novo mais tarde.']);

    expect(file_exists((string) $fake->caminho))->toBeFalse()
        ->and(Cache::lock('transcrever')->get())->toBeTrue();
});

it('libera a trava depois de transcrever', function (): void {
    usarTranscritor(new TranscritorDeTeste);

    enviarAudio()->assertOk();

    $trava = Cache::lock('transcrever', 5);

    expect($trava->get())->toBeTrue();
    $trava->release();
});

it('responde 503 com Retry-After quando outra transcrição segura a trava', function (): void {
    $fake = new TranscritorDeTeste;
    usarTranscritor($fake);
    $trava = Cache::lock('transcrever', 50);
    $trava->get();

    enviarAudio()->assertStatus(503)
        ->assertHeader('Retry-After')
        ->assertJson(['message' => 'O transcritor está ocupado. Tente de novo em instantes.']);

    expect($fake->caminho)->toBeNull();
    $trava->release();
    enviarAudio()->assertOk();
});

it('recusa áudio ausente, grande demais e de tipo não aceito', function (): void {
    usarTranscritor(new TranscritorDeTeste);

    post('/transcrever', ['idioma' => 'en'], ['Accept' => 'application/json'])->assertStatus(422)
        ->assertJson(['message' => 'Envie uma gravação de áudio.']);
    enviarAudio(['audio' => audioDeTeste(2049)])->assertStatus(422)
        ->assertJson(['message' => 'A gravação passa de 2 MB. Grave um trecho mais curto.']);
    enviarAudio(['audio' => audioDeTeste(10, 'image/png', 'foto.png')])->assertStatus(422)
        ->assertJson(['message' => 'Formato de áudio não aceito.']);
    enviarAudio(['audio' => 'texto'])->assertStatus(422);
});

it('aceita exatamente 2 MB e os formatos dos navegadores', function (): void {
    usarTranscritor(new TranscritorDeTeste);

    enviarAudio(['audio' => audioDeTeste(2048)])->assertOk();

    foreach ([['audio/ogg', 'a.ogg'], ['audio/mp4', 'a.m4a'], ['video/webm', 'a.webm'], ['audio/wav', 'a.wav'], ['audio/mpeg', 'a.mp3']] as [$tipo, $nome]) {
        enviarAudio(['audio' => audioDeTeste(10, $tipo, $nome)])->assertOk();
    }
});

it('recusa idioma ausente, desconhecido e sem conteúdo no catálogo', function (): void {
    usarTranscritor(new TranscritorDeTeste);

    enviarAudio(['idioma' => null])->assertStatus(422);
    enviarAudio(['idioma' => 'xx'])->assertStatus(422)->assertJson(['message' => 'Escolha um idioma disponível.']);
    enviarAudio(['idioma' => 'es'])->assertStatus(422);
    enviarAudio(['idioma' => 'pt-BR'])->assertOk();
});

it('responde 429 quando o IP passa do limite por minuto', function (): void {
    config(['phparrot.transcricao.por_minuto' => 2]);
    usarTranscritor(new TranscritorDeTeste);

    enviarAudio()->assertOk();
    enviarAudio()->assertOk();
    enviarAudio()->assertStatus(429)
        ->assertJson(['message' => 'Muitas transcrições em pouco tempo. Tente de novo daqui a pouco.'])
        ->assertHeader('Retry-After');
});

it('separa os contadores por IP e segura o teto do dia para todos', function (): void {
    config(['phparrot.transcricao.por_minuto' => 1, 'phparrot.transcricao.por_dia' => 2]);
    usarTranscritor(new TranscritorDeTeste);

    withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);
    enviarAudio()->assertOk();
    enviarAudio()->assertStatus(429);

    withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
    enviarAudio()->assertOk();

    withServerVariables(['REMOTE_ADDR' => '10.0.0.3']);
    enviarAudio()->assertStatus(429);
});

it('o motor manda o arquivo, a subtag do idioma e a duração ao whisper-server', function (): void {
    Http::fake(['phparrot-whisper:8080/*' => Http::response(['text' => " Olá, tudo bem?\n"])]);
    $caminho = tempnam(sys_get_temp_dir(), 'audio');
    file_put_contents((string) $caminho, 'bytes');

    $texto = new TranscritorWhisperServer('http://phparrot-whisper:8080/', 30000)->transcrever((string) $caminho, Idioma::PtBr);
    unlink((string) $caminho);

    expect($texto)->toBe('Olá, tudo bem?');

    Http::assertSent(function (PedidoHttp $pedido): bool {
        $campos = [];

        foreach ($pedido->data() as $parte) {
            if (is_array($parte) && is_string($parte['name'] ?? null)) {
                $campos[$parte['name']] = $parte['contents'] ?? null;
            }
        }

        return $pedido->url() === 'http://phparrot-whisper:8080/inference'
            && $campos['language'] === 'pt'
            && $campos['duration'] === '30000'
            && $campos['response_format'] === 'json'
            && $campos['file'] === 'bytes'
            && ! array_key_exists('prompt', $campos);
    });
});

it('o motor lança indisponível quando o whisper-server falha ou devolve lixo', function (): void {
    $caminho = tempnam(sys_get_temp_dir(), 'audio');
    file_put_contents((string) $caminho, 'bytes');
    $motor = new TranscritorWhisperServer('http://phparrot-whisper:8080', 30000);

    Http::fake(['*' => Http::response(['error' => 'FFmpeg conversion failed.'], 500)]);
    expect(fn () => $motor->transcrever((string) $caminho, Idioma::En))->toThrow(TranscricaoIndisponivel::class);

    Http::fake(['*' => Http::response(['outro' => 1])]);
    expect(fn () => $motor->transcrever((string) $caminho, Idioma::En))->toThrow(TranscricaoIndisponivel::class);

    unlink((string) $caminho);
});

it('não registra o texto transcrito numa falha do motor', function (): void {
    $arquivo = tempnam(sys_get_temp_dir(), 'phparrot-log-');
    config(['logging.default' => 'teste', 'logging.channels.teste' => ['driver' => 'single', 'path' => $arquivo, 'level' => 'debug']]);
    Http::fake(['*' => Http::response(['text' => 'Frase muito particular do visitante', 'error' => 'quebrou'], 500)]);

    enviarAudio()->assertStatus(503);

    $registro = (string) file_get_contents((string) $arquivo);
    unlink((string) $arquivo);

    expect($registro)->toContain('O serviço de transcrição recusou o pedido.')
        ->and($registro)->not->toContain('Frase muito particular')
        ->and($registro)->not->toContain('fala.webm');
});
