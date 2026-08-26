#!/usr/bin/env bash
# Esegue tutte le suite del modulo. Nessuna richiede database.
# Uso:  bash tests/run.sh
set -u

cd "$(dirname "$0")/.." || exit 1

suites=(smoke energy-scale list-price residenze resource-form pdf)
failed=0

for suite in "${suites[@]}"; do
    printf '\n=== %s ===\n' "$suite"
    if ! php "tests/${suite}.php"; then
        printf '!!! FALLITA: %s\n' "$suite"
        failed=1
    fi
done

printf '\n'
if [ "$failed" -eq 0 ]; then
    echo "TUTTE LE SUITE PASSATE"
else
    echo "ALMENO UNA SUITE FALLITA"
fi

exit "$failed"
