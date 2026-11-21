#!/usr/bin/env bash
# Receive a theme release on stdin (a .tar.gz from `git archive`) and deploy it.
#
# This is the forced command for the GitHub deploy key (UHOF_DEPLOY_KEY), so the key can do
# nothing else. The installed copy lives OUTSIDE the theme, at $SITE/ops/deploy-receive.sh,
# so swapping the theme folder never pulls the running script out from under itself; each
# successful deploy refreshes that copy from the release it just installed.
#
# The release name comes from the client's requested command (SSH_ORIGINAL_COMMAND).
# Steps: unpack to a staging folder, check it, back up the live theme, swap, reindex,
# flush caches, smoke-test the home page, roll back if that fails.
# Prints "RESULT: ..." as its last line; the full log goes to $SITE/logs/deploy.log.
set -euo pipefail

# UH_* overrides exist only for testing this script on a local copy; sshd ignores client env.
SITE=${UH_SITE:-/sites/ultimatehall.org}
WP_ROOT=${UH_WP_ROOT:-$SITE/files}
THEMES=${UH_THEMES:-$WP_ROOT/wp-content/themes}
URL=${UH_URL:-https://ultimatehall.org}
THEME=$THEMES/ultimate-hall
BACKUPS=$SITE/theme-backups
LOGFILE=$SITE/logs/deploy.log
KEEP=5
MAX_BYTES=50000000
WP="${UH_WP:-/usr/local/bin/wp} --path=$WP_ROOT"
TS=$(date +%Y%m%d-%H%M%S)
STAGE=$THEMES/.ultimate-hall-incoming-$TS
OLD=$THEMES/.ultimate-hall-previous-$TS

REF=$(printf '%s' "${SSH_ORIGINAL_COMMAND:-manual}" | tr -cd 'A-Za-z0-9._/@-' | cut -c1-80)
[ -n "$REF" ] || REF=manual

mkdir -p "$BACKUPS" "$(dirname "$LOGFILE")"
exec 3>>"$LOGFILE"
log() { echo "[$(date -u +'%F %T')] $*" >&3; }
result() { log "RESULT: $*"; echo "RESULT: $*"; }
fail() { result "failed: $*"; exit 1; }

exec 9>"$SITE/.deploy.lock"
flock -n 9 || fail "another deploy is running"

cleanup() { rm -rf "$STAGE"; }
trap cleanup EXIT

log "deploy $REF start"

# 1. Unpack. GNU tar strips leading "/" and "../" from member names.
mkdir -p "$STAGE"
head -c "$MAX_BYTES" | tar -xz -C "$STAGE" --no-same-owner --no-same-permissions 2>&3 \
  || fail "could not unpack the release"

# 2. Check it looks like this theme and every PHP file parses.
for f in style.css functions.php single-member.php; do
  [ -f "$STAGE/$f" ] || fail "release is missing $f"
done
grep -q 'Theme Name' "$STAGE/style.css" || fail "style.css has no theme header"
bad=$(find "$STAGE" -name '*.php' -not -path '*/node_modules/*' -print0 \
  | xargs -0 -n1 php -l 2>&1 | grep -v '^No syntax errors' || true)
[ -z "$bad" ] || { log "$bad"; fail "PHP syntax errors in the release"; }
printf '%s\n' "$REF" > "$STAGE/.deployed-release"

# 3. Back up the live theme.
tar -czf "$BACKUPS/theme-$TS.tgz" -C "$THEMES" ultimate-hall
log "backup $BACKUPS/theme-$TS.tgz"

# 4. Swap. Each mv is a rename on one filesystem, so the site sees one theme or the other.
mv "$THEME" "$OLD"
mv "$STAGE" "$THEME"
log "swapped in $REF"

rollback() {
  log "rolling back: $*"
  rm -rf "$THEME"
  mv "$OLD" "$THEME"
  $WP cache flush >&3 2>&1 || true
  $WP spinupwp cache purge-site >&3 2>&1 || true
  fail "$* (rolled back to the previous theme)"
}

# 5. Rebuild derived data and clear caches.
$WP uh reindex >&3 2>&1 || rollback "wp uh reindex failed"
$WP cache flush >&3 2>&1 || true
$WP spinupwp cache purge-site >&3 2>&1 || true

# 6. Smoke test through nginx: home page and one member page must be 200 with no PHP fatal.
for path in / /members/; do
  body=$(curl -s -m 30 -w '\n%{http_code}' "$URL$path" || true)
  code=$(printf '%s' "$body" | tail -1)
  [ "$code" = "200" ] || rollback "$path returned $code"
  printf '%s' "$body" | grep -q 'critical error on this website' && rollback "$path shows a critical error"
done

# 7. Done: drop the previous copy, prune old backups, refresh the installed receive script.
rm -rf "$OLD"
ls -1t "$BACKUPS"/theme-*.tgz 2>/dev/null | tail -n +$((KEEP + 1)) | xargs -r rm -f
if [ -f "$THEME/ops/deploy-receive.sh" ]; then
  mkdir -p "$SITE/ops"
  cp "$THEME/ops/deploy-receive.sh" "$SITE/ops/.deploy-receive.new"
  mv "$SITE/ops/.deploy-receive.new" "$SITE/ops/deploy-receive.sh"
fi

# 8. WebP copies of any images that don't have them yet, in the background so the deploy returns
#    now. Closes the lock and log fds so the next deploy isn't blocked; purges the page cache
#    when done so cached pages pick up the copies. Output goes to $SITE/logs/webp.log.
setsid nohup bash -c "$WP uh webp && $WP spinupwp cache purge-site" \
  >>"$SITE/logs/webp.log" 2>&1 </dev/null 3>&- 9>&- &
log "started wp uh webp in the background"

result "deployed $REF"
