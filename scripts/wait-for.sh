#!/bin/sh
# Minimal TCP wait helper: wait-for.sh host port [timeout_seconds]
set -e
host="$1"; port="$2"; timeout="${3:-30}"
i=0
while ! nc -z "$host" "$port" 2>/dev/null; do
  i=$((i + 1))
  if [ "$i" -ge "$timeout" ]; then
    echo "timeout waiting for $host:$port" >&2
    exit 1
  fi
  sleep 1
done
echo "$host:$port is up"
