from fastapi import FastAPI

app = FastAPI(title="phparrot-gateway", docs_url=None, redoc_url=None, openapi_url=None)


@app.get("/health")
def saude() -> dict[str, str]:
    return {"status": "ok"}
