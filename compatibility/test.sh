#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mode="${1:-core}"
case "$mode" in
    core|spatie) ;;
    *) echo 'Usage: compatibility/test.sh core|spatie [Pest arguments]' >&2; exit 2 ;;
esac
shift || true
exec "compatibility/$mode/vendor/bin/pest" --test-directory ../../tests \
    --bootstrap "compatibility/$mode/vendor/autoload.php" "$@"
