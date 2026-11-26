#!/bin/sh
set -e

if [ ! -f package.json ]; then
    echo "[frontend] package.json not found, copying template..."
    cp /tmp/template/package.json ./
fi

if [ ! -d node_modules ]; then
    echo "[frontend] installing dependencies..."
    npm install
fi

exec "$@"
