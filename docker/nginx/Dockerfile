FROM nginx:1.30-alpine

RUN rm -rf /etc/nginx/conf.d/default.conf

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
EXPOSE 443
