# Static frontend — no build step. Nginx serves the files as-is.
FROM nginx:1.27-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY index.html /usr/share/nginx/html/index.html
COPY favicon.ico /usr/share/nginx/html/favicon.ico
COPY src /usr/share/nginx/html/src
COPY assets /usr/share/nginx/html/assets
EXPOSE 80
