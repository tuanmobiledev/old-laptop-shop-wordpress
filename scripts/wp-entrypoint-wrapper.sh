#!/bin/bash
# OSCAR Shop — entrypoint wrapper
#
# Runs the official WordPress entrypoint first (which handles DB readiness,
# volume copy, file permissions), then hands off to Apache.
#
# IMPORTANT: We do NOT call wp-init.sh directly here. Instead, the Dockerfile
# RUNs scripts/patch-entrypoint.sh which inserts a wp-init.sh call into the
# official /usr/local/bin/docker-entrypoint.sh, just before its final
# `exec "$@"`. This is necessary because the official entrypoint ends with
# `exec apache2-foreground` — it never returns — so any command after
# `docker-entrypoint.sh "$@"` in this wrapper would never run.

set -e

# Run the original WordPress entrypoint (now patched to invoke wp-init.sh
# before launching apache).
exec /usr/local/bin/docker-entrypoint.sh "$@"
