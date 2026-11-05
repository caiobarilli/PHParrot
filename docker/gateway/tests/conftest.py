from collections.abc import Iterator
from pathlib import Path

import pytest
from fastapi import FastAPI
from fastapi.testclient import TestClient

from gateway.app import criar_app
from gateway.config import Config

TOKEN = "a1" * 32
MODELO = "llama3.2:3b"


@pytest.fixture
def anyio_backend() -> str:
    return "asyncio"


@pytest.fixture
def arquivo_do_token(tmp_path: Path) -> Path:
    arquivo = tmp_path / "gateway-token"
    arquivo.write_text(TOKEN)

    return arquivo


@pytest.fixture
def config(arquivo_do_token: Path) -> Config:
    return Config(token_arquivo=arquivo_do_token, modelos=(MODELO,), concorrencia=1)


@pytest.fixture
def app(config: Config) -> FastAPI:
    return criar_app(config)


@pytest.fixture
def cliente(app: FastAPI) -> Iterator[TestClient]:
    with TestClient(app) as cliente:
        yield cliente


def autorizacao(token: str = TOKEN) -> dict[str, str]:
    return {"Authorization": f"Bearer {token}"}
