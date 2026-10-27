# Static frontend — no build step. Nginx serves the files as-is.
FROM nginx:1.30.6-alpine@sha256:47928bf67eb62a1863cb08d9ecb52d3e704304ba614eaee17884204677702951
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY index.html /usr/share/nginx/html/index.html
COPY favicon.ico /usr/share/nginx/html/favicon.ico
COPY src /usr/share/nginx/html/src
COPY assets /usr/share/nginx/html/assets
EXPOSE 80
