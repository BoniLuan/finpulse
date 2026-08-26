#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(git rev-parse --show-toplevel)"
git config core.hooksPath .githooks
chmod +x .githooks/post-commit
git_dir="$(git rev-parse --git-dir)"
state_file="$git_dir/finpulse-last-deployed"
if [[ ! -f "$state_file" ]]; then
    git rev-parse HEAD >"$state_file"
    echo "Initialized deployment state at $(git rev-parse --short HEAD)."
fi
echo "FinPulse post-commit deployment enabled."
echo "Disable with: git config --unset core.hooksPath"
