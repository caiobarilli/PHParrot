import anyio
import httpx2
import pytest
from fastapi import FastAPI

from gateway.concorrencia import Limitador
from gateway.erros import ErroOpenAI


def app_com_rota_lenta(app: FastAPI, entrou: anyio.Event, liberar: anyio.Event) -> FastAPI:
    limitador: Limitador = app.state.limitador

    @app.get("/teste/lento")
    async def lento() -> dict[str, str]:
        with limitador.vaga():
            entrou.set()
            await liberar.wait()

        return {"status": "ok"}

    return app


@pytest.mark.anyio
async def test_com_concorrencia_1_o_segundo_pedido_leva_503_na_hora(app: FastAPI) -> None:
    entrou, liberar = anyio.Event(), anyio.Event()
    transporte = httpx2.ASGITransport(app=app_com_rota_lenta(app, entrou, liberar))
    respostas: list[httpx2.Response] = []

    async with httpx2.AsyncClient(transport=transporte, base_url="http://gateway") as cliente:

        async def primeiro() -> None:
            respostas.append(await cliente.get("/teste/lento"))

        async with anyio.create_task_group() as grupo:
            grupo.start_soon(primeiro)
            await entrou.wait()

            with anyio.fail_after(2):
                segundo = await cliente.get("/teste/lento")

            liberar.set()

        terceiro = await cliente.get("/teste/lento")

    assert segundo.status_code == 503
    assert segundo.headers["retry-after"] == "5"
    assert segundo.json()["error"] == {
        "message": "O gateway está ocupado. Tente de novo em instantes.",
        "type": "server_error",
        "param": None,
        "code": "ocupado",
    }
    assert [resposta.status_code for resposta in respostas] == [200]
    assert terceiro.status_code == 200
    assert app.state.limitador.ocupadas == 0


def test_vaga_e_devolvida_mesmo_com_excecao() -> None:
    limitador = Limitador(1)

    with pytest.raises(RuntimeError), limitador.vaga():
        raise RuntimeError

    assert limitador.ocupadas == 0

    with limitador.vaga():
        assert limitador.ocupadas == 1


def test_duas_vagas_aceitam_dois_e_recusam_o_terceiro() -> None:
    limitador = Limitador(2)

    with limitador.vaga(), limitador.vaga():
        assert limitador.ocupadas == 2

        with pytest.raises(ErroOpenAI, match="ocupado"), limitador.vaga():
            pass

    assert limitador.ocupadas == 0
