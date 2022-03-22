#!/usr/bin/env bash
set -o errexit # Abort if any command fails

rm -rf build
docker run --rm --name slate -v $(pwd)/build:/srv/slate/build -v $(pwd)/source:/srv/slate/source slatedocs/slate
