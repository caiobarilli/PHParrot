FROM nginx:1.30.7-alpine@sha256:caf4e1e032a20f9c3c5e28fc7f25ffd768ad8677b4975c54193075997b3a97e3

RUN apk del --no-cache curl \
    && apk upgrade --no-cache brotli-libs libexpat \
    && sed -i -e '/^user /d' -e 's#^pid .*#pid /tmp/nginx.pid;#' /etc/nginx/nginx.conf \
    && chown -R 101:101 /var/cache/nginx \
    && nginx -t \
    && rm -f /tmp/nginx.pid

USER 101
