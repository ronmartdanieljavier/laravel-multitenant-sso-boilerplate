ARG APP_IMAGE
FROM ${APP_IMAGE} AS app

FROM nginx:alpine
COPY ecs.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
