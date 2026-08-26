#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(git rev-parse --show-toplevel)"
git_dir="$(git rev-parse --git-dir)"
rollback_file="$git_dir/finpulse-rollback"
state_file="$git_dir/finpulse-last-deployed"
project_name="${COMPOSE_PROJECT_NAME:-finpulse}"

[[ -s "$rollback_file" ]] || { echo "No rollback snapshot is available." >&2; exit 1; }

services=()
previous_commit=""
while IFS='|' read -r service value; do
    if [[ "$service" == commit ]]; then previous_commit="$value"; continue; fi
    [[ -n "$service" && -n "$value" ]] || continue
    docker image inspect "$value" >/dev/null
    docker image tag "$value" "$project_name-$service:latest"
    services+=("$service")
done <"$rollback_file"

((${#services[@]} > 0)) || { echo "No service images in snapshot." >&2; exit 1; }
docker compose -f docker-compose.yml up -d --no-deps --force-recreate "${services[@]}"
[[ -z "$previous_commit" ]] || echo "$previous_commit" >"$state_file"
echo "Restored services: ${services[*]}"
echo "Database migrations are not reversed."
