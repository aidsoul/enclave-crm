FROM node:22-alpine

WORKDIR /app

RUN apk add --no-cache git

COPY docker/frontend/package.json* ./

RUN if [ -f package.json ]; then npm install; fi

COPY docker/frontend/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 5173

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
