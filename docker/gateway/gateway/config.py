import os
from collections.abc import Mapping
from dataclasses import dataclass
from pathlib import Path


class ConfiguracaoInvalida(Exception):
    pass


@dataclass(frozen=True, slots=True)
class Config:
    token_arquivo: Path
    modelos: tuple[str, ...]
    concorrencia: int

    @classmethod
    def do_ambiente(cls, ambiente: Mapping[str, str] | None = None) -> "Config":
        valores = os.environ if ambiente is None else ambiente

        token_arquivo = valores.get("GATEWAY_TOKEN_ARQUIVO", "").strip()
        if token_arquivo == "":
            raise ConfiguracaoInvalida("GATEWAY_TOKEN_ARQUIVO ausente")

        modelos = tuple(modelo.strip() for modelo in valores.get("GATEWAY_MODELOS", "").split(",") if modelo.strip())
        if not modelos:
            raise ConfiguracaoInvalida("GATEWAY_MODELOS vazio")

        try:
            concorrencia = int(valores.get("GATEWAY_CONCORRENCIA", ""))
        except ValueError as erro:
            raise ConfiguracaoInvalida("GATEWAY_CONCORRENCIA precisa ser um inteiro") from erro
        if concorrencia < 1:
            raise ConfiguracaoInvalida("GATEWAY_CONCORRENCIA precisa ser pelo menos 1")

        return cls(token_arquivo=Path(token_arquivo), modelos=modelos, concorrencia=concorrencia)
