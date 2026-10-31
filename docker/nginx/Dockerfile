FROM nginx:1.30.6-alpine@sha256:0e52bd054126cdc040b1276c0af347aeb02a41b9cfe305d6094767ac30e97949

RUN apk del --no-cache curl \
    && apk upgrade --no-cache brotli-libs \
    && sed -i -e '/^user /d' -e 's#^pid .*#pid /tmp/nginx.pid;#' /etc/nginx/nginx.conf \
    && chown -R 101:101 /var/cache/nginx \
    && nginx -t \
    && rm -f /tmp/nginx.pid

USER 101
