#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
database=$(mktemp "${TMPDIR:-/tmp}/legal-removal.XXXXXX")
trap 'rm -f "$database"' EXIT
LEGAL_TRANSLATION_LIFECYCLE_DATABASE="$database" LEGAL_TRANSLATION_LIFECYCLE_PHASE=seed \
    bash compatibility/test.sh spatie tests/Integration/DependencyRemovalTest.php
LEGAL_TRANSLATION_LIFECYCLE_DATABASE="$database" LEGAL_TRANSLATION_LIFECYCLE_PHASE=verify \
    bash compatibility/test.sh core tests/Integration/DependencyRemovalTest.php
