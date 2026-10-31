FROM nginx:1.31.7-alpine@sha256:892bb2152464f0c5d8972593a5f5afaf8054552580b699d95f8ff4af08c2d33d

RUN apk del --no-cache curl \
    && apk upgrade --no-cache brotli-libs \
    && sed -i -e '/^user /d' -e 's#^pid .*#pid /tmp/nginx.pid;#' /etc/nginx/nginx.conf \
    && chown -R 101:101 /var/cache/nginx \
    && nginx -t \
    && rm -f /tmp/nginx.pid

USER 101
