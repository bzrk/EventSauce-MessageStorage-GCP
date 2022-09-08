#!/bin/bash

SCRIPT=$(readlink -f "$0")
BASEDIR=$(dirname "$SCRIPT")

U_ID=$(id -u)
G_ID=$(id -g)

TAG="bzrk-eventsource-firestore"

if [ "$1" == "build" ]; then
    docker build --tag "$TAG" ./docker/php/;
    echo "Building successful...";
    exit 0;
fi
echo $BASEDIR;
docker run --rm --user "${U_ID}:${G_ID}" -w "/app" -v "${BASEDIR}:/app" "$TAG" "$@"