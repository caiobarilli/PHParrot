FROM debian:13.7-slim@sha256:f6b08b09a05725f9431502d3b5714f5c4576e82c1a84fa1debe0c24a7e0c3ec4 AS compilacao

ARG WHISPER_TAG=v1.9.5
ARG WHISPER_COMMIT=d1be6fde11ac6e0407606b4e42fe72d34add8037

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    git \
    cmake \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

RUN git clone --depth 1 --branch "${WHISPER_TAG}" https://github.com/ggml-org/whisper.cpp /src \
    && test "$(git -C /src rev-parse HEAD)" = "${WHISPER_COMMIT}"

COPY patches /patches

RUN git -C /src apply --check /patches/*.patch \
    && git -C /src apply /patches/*.patch

WORKDIR /src

RUN cmake -B build \
        -DCMAKE_BUILD_TYPE=Release \
        -DBUILD_SHARED_LIBS=OFF \
        -DGGML_NATIVE=OFF \
        -DGGML_AVX2=ON \
        -DGGML_FMA=ON \
        -DGGML_F16C=ON \
        -DWHISPER_BUILD_TESTS=OFF \
    && cmake --build build --config Release --target whisper-server -j "$(nproc)"

FROM debian:13.7-slim@sha256:f6b08b09a05725f9431502d3b5714f5c4576e82c1a84fa1debe0c24a7e0c3ec4 AS modelo

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

ARG MODELO=base
ARG MODELO_SHA1

RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates curl \
    && rm -rf /var/lib/apt/lists/*

RUN test -n "${MODELO_SHA1}" \
    && curl -fsSL -o /modelo.bin "https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-${MODELO}.bin" \
    && echo "${MODELO_SHA1}  /modelo.bin" | sha1sum -c -

FROM debian:13.7-slim@sha256:f6b08b09a05725f9431502d3b5714f5c4576e82c1a84fa1debe0c24a7e0c3ec4

ARG MODELO=base

RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg libgomp1 \
    && rm -rf /var/lib/apt/lists/*

RUN useradd --system --uid 10001 --no-create-home --shell /usr/sbin/nologin whisper \
    && mkdir -p /models

COPY --from=compilacao /src/build/bin/whisper-server /usr/local/bin/whisper-server
COPY --from=modelo /modelo.bin /models/ggml-${MODELO}.bin
COPY --chmod=755 iniciar.sh /usr/local/bin/iniciar.sh

ENV MODELO=${MODELO}

USER 10001

EXPOSE 8080

CMD ["/usr/local/bin/iniciar.sh"]
