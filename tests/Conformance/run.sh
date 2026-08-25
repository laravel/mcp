#!/usr/bin/env bash
set -uo pipefail

CONFORMANCE="${CONFORMANCE_PACKAGE:-@modelcontextprotocol/conformance@alpha}"
REVISION="${CONFORMANCE_REVISION:-2026-07-28}"
PORT="${CONFORMANCE_PORT:-8001}"

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$DIR/../.." && pwd)"
SUITE="${1:-all}"

rm -rf "$DIR/results" "$DIR/logs"
mkdir -p "$DIR/logs"

status=0

run_server() {
    php "$ROOT/vendor/bin/testbench" serve --port="$PORT" >"$DIR/logs/serve.log" 2>&1 &
    local pid=$!
    trap 'kill '"$pid"' 2>/dev/null' EXIT

    for _ in $(seq 1 30); do
        if curl -sf -o /dev/null "http://127.0.0.1:$PORT/conformance" -X POST \
            -H 'Content-Type: application/json' \
            -H "MCP-Protocol-Version: $REVISION" \
            -H 'MCP-Method: server/discover' \
            -d '{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"'"$REVISION"'","io.modelcontextprotocol/clientCapabilities":{}}}}'; then
            break
        fi
        sleep 1
    done

    npx --yes "$CONFORMANCE" server \
        --url "http://127.0.0.1:$PORT/conformance" \
        --requirements "$REVISION" \
        --expected-failures "$DIR/conformance-baseline.yml" \
        --output-dir "$DIR/results/server" || status=1

    kill $pid 2>/dev/null
    trap - EXIT

    php "$DIR/score.php" server
}

run_client() {
    npx --yes "$CONFORMANCE" client \
        --command "php $DIR/client.php" \
        --requirements "$REVISION" \
        --expected-failures "$DIR/conformance-baseline.yml" \
        --output-dir "$DIR/results/client" || status=1

    php "$DIR/score.php" client
}

case "$SUITE" in
    server) run_server ;;
    client) run_client ;;
    all) run_server; run_client ;;
    *) echo "Usage: run.sh [server|client|all]" >&2; exit 1 ;;
esac

exit $status
