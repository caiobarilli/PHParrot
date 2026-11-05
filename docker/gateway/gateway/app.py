from fastapi import APIRouter, Depends, FastAPI, Request

from gateway.autenticacao import exigir_token
from gateway.concorrencia import Limitador
from gateway.config import Config
from gateway.erros import registrar_tratadores
from gateway.modelos import listar_modelos

v1 = APIRouter(prefix="/v1", dependencies=[Depends(exigir_token)])


@v1.get("/models")
def modelos(request: Request) -> dict[str, object]:
    config: Config = request.app.state.config

    return listar_modelos(config)


def criar_app(config: Config | None = None) -> FastAPI:
    app = FastAPI(title="phparrot-gateway", docs_url=None, redoc_url=None, openapi_url=None)
    app.state.config = config if config is not None else Config.do_ambiente()
    app.state.limitador = Limitador(app.state.config.concorrencia)

    registrar_tratadores(app)

    @app.get("/health")
    def saude() -> dict[str, str]:
        return {"status": "ok"}

    app.include_router(v1)

    return app
