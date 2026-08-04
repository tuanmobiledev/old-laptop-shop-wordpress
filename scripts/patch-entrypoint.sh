#!/bin/sh
# OSCAR Shop — patch WordPress base image docker-entrypoint.sh
# to invoke wp-init.sh before final exec.
#
# The wrapper script (wp-entrypoint-wrapper.sh) calls docker-entrypoint.sh
# which ends with `exec "$@"` — that means the wrapper waits forever for
# apache to exit, and wp-init.sh never runs. This script fixes that by
# inserting a wp-init.sh call into docker-entrypoint.sh, just before its
# final `exec "$@"`.

set -e

ENT=/usr/local/bin/docker-entrypoint.sh
MARK="# OSCAR: run auto-fix before exec"

if grep -q "$MARK" "$ENT" 2>/dev/null; then
  echo "[patch-entrypoint] Already patched, skipping"
  exit 0
fi

# Insert wp-init.sh call before the final `exec "$@"`.
#
# IMPORTANT: the entire perl replacement string MUST be wrapped in single
# quotes so that shell does NOT expand $@. Otherwise it gets replaced with
# "" (no positional args in this script) and the patched file ends with
# `exec ""` — which causes bash to throw "exec: : not found" and the
# container to exit 127 in a restart loop.
#
# Use a heredoc to feed the perl replacement so we don't have to worry
# about shell escaping $@ vs \$@.
# NOTE on perl escaping: in the replacement side, `$@` is a SPECIAL variable
# (perl's eval error). If we write `$@` literally, perl substitutes it with the
# empty eval error string, so the patched file ends with `exec ""` — which
# crashes bash with "exec: : not found". To output a literal `$@`, we must
# write `\$@` in the replacement (perl treats `\$` as an escape for `$`).
perl -i -pe '
  s{^exec "\$@"$}{
'"$MARK"'
/usr/local/bin/wp-init.sh || true
exec \$@
  }
' "$ENT"

echo "[patch-entrypoint] Patched docker-entrypoint.sh. New tail:"
tail -6 "$ENT"
