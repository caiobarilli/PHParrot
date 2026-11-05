from fastapi.testclient import TestClient

from gateway.app import app

cliente = TestClient(app)


def test_health_responde_ok() -> None:
    resposta = cliente.get("/health")

    assert resposta.status_code == 200
    assert resposta.json() == {"status": "ok"}


def test_documentacao_automatica_fica_desligada() -> None:
    for caminho in ("/docs", "/redoc", "/openapi.json"):
        assert cliente.get(caminho).status_code == 404
