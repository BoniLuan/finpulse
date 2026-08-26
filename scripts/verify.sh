#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(git rev-parse --show-toplevel)"
base_ref="${1:-HEAD}"
git rev-parse --verify "$base_ref" >/dev/null

mapfile -t changed < <(
    { git diff --name-only "$base_ref" --; git ls-files --others --exclude-standard; } |
        sed '/^$/d' | sort -u
)

if ((${#changed[@]} == 0)); then
    echo "No changes found relative to $base_ref."
    exit 0
fi

printf 'Changed files:\n'
printf '  %s\n' "${changed[@]}"

touches() {
    local pattern="$1" file
    for file in "${changed[@]}"; do
        [[ "$file" == $pattern ]] && return 0
    done
    return 1
}

if touches "docker-compose.yml" || touches "compose.dev.yml"; then
    docker compose -f docker-compose.yml config --quiet
fi

if touches "services/api/*"; then
    echo "Verifying API..."
    docker build --target development -t finpulse-api-test services/api
    docker run --rm -v "$PWD/services/api/tests:/var/www/html/tests:ro" \
        finpulse-api-test sh -c 'composer test && composer lint'
fi

if touches "services/ai-worker/*"; then
    echo "Verifying AI worker..."
    docker build -t finpulse-ai-worker-test services/ai-worker
    docker run --rm finpulse-ai-worker-test sh -c 'pytest -q && ruff check . && mypy app'
fi

if touches "services/web/*"; then
    echo "Verifying web..."
    docker run --rm -v "$PWD/services/web:/app:ro" -w /app node:20-alpine \
        sh -c 'for file in src/*.js; do node --check "$file"; done'
fi

echo "Affected-change verification passed."
