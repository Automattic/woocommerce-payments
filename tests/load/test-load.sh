#!/usr/bin/env bash

echo "🚀 Running k6 load tests...";

docker compose -f ./tests/load/docker-compose.yml run --rm -it --service-ports \
	k6 run "$@"
