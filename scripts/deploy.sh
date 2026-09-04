#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(git rev-parse --show-toplevel)"
git_dir="$(git rev-parse --git-dir)"
state_file="$git_dir/finpulse-last-deployed"
rollback_file="$git_dir/finpulse-rollback"
health_url="${FINPULSE_HEALTH_URL:-https://finpulse.boniluan.com/api/v1/health}"
site_url="${FINPULSE_SITE_URL:-https://finpulse.boniluan.com/}"
target_commit="$(git rev-parse HEAD)"
rollback_needed=0

rollback_on_error() {
    local status=$?
    trap - ERR
    if ((rollback_needed == 1)); then
        echo "Deployment command failed; restoring previous images." >&2
        scripts/rollback.sh || echo "Automatic rollback also failed; run make rollback." >&2
    fi
    exit "$status"
}

trap rollback_on_error ERR

exec 9>"$git_dir/finpulse-deploy.lock"
flock -n 9 || { echo "Another deployment is running." >&2; exit 1; }

if [[ "${FINPULSE_ALLOW_DIRTY:-0}" != 1 ]] && [[ -n "$(git status --porcelain)" ]]; then
    echo "Refusing to deploy a dirty working tree. Commit intended files first." >&2
    exit 1
fi

if [[ -n "${BASE_REF:-}" ]]; then
    base_ref="$BASE_REF"
elif [[ -f "$state_file" ]] && git cat-file -e "$(cat "$state_file")^{commit}" 2>/dev/null; then
    base_ref="$(cat "$state_file")"
elif git rev-parse HEAD^ >/dev/null 2>&1; then
    base_ref=HEAD^
else
    base_ref=HEAD
fi

mapfile -t changed < <(git diff --name-only "$base_ref" "$target_commit" -- | sed '/^$/d' | sort -u)
if ((${#changed[@]} == 0)); then
    echo "Commit $target_commit is already deployed."
    exit 0
fi

scripts/verify.sh "$base_ref"

declare -A selected=()
for file in "${changed[@]}"; do
    case "$file" in
        docker-compose.yml)
            for service in gateway api collector scheduler ai-worker web; do selected[$service]=1; done ;;
        services/api/*) selected[api]=1; selected[collector]=1; selected[scheduler]=1 ;;
        services/ai-worker/*) selected[ai-worker]=1 ;;
        services/web/*) selected[web]=1 ;;
        infra/gateway/*) selected[gateway]=1 ;;
    esac
done

if ((${#selected[@]} == 0)); then
    echo "$target_commit" >"$state_file"
    echo "No runtime files changed; marked commit as deployed."
    exit 0
fi

mapfile -t services < <(printf '%s\n' "${!selected[@]}" | sort)
printf 'Affected services: %s\n' "${services[*]}"

: >"$rollback_file"
printf 'commit|%s\n' "$base_ref" >>"$rollback_file"
for service in "${services[@]}"; do
    container_id="$(docker compose -f docker-compose.yml ps -q "$service")"
    if [[ -n "$container_id" ]]; then
        printf '%s|%s\n' "$service" "$(docker inspect --format '{{.Image}}' "$container_id")" >>"$rollback_file"
    fi
done

docker compose -f docker-compose.yml build "${services[@]}"
if [[ -n "${selected[api]:-}" || -n "${selected[collector]:-}" || -n "${selected[scheduler]:-}" ]]; then
    docker compose -f docker-compose.yml run --rm --no-deps api php bin/console migrate
fi
rollback_needed=1
docker compose -f docker-compose.yml up -d --no-deps "${services[@]}"

healthy=0
for attempt in {1..12}; do
    if curl --fail --silent --show-error "$health_url" >/dev/null &&
        curl --fail --silent --show-error "$site_url" >/dev/null; then
        healthy=1
        break
    fi
    sleep 5
done

if ((healthy == 0)); then
    echo "Health checks failed; restoring previous images." >&2
    rollback_needed=0
    scripts/rollback.sh
    exit 1
fi

echo "$target_commit" >"$state_file"
echo "Deployment succeeded: $target_commit"
