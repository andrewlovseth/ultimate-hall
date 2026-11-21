#!/usr/bin/env bash
# Daily read-only health check for ultimatehall.org, run on the server as the site user.
# GitHub Actions (.github/workflows/health.yml) runs it over SSH with a key that can run nothing else.
# Exit 0 = green. Exit 1 = alerts, each on a line starting "ALERT:".
# Adapted from esassoc-ops scripts/check/health.sh; no sudo here, so no apt, services or fail2ban.
set -u
SITE=/sites/ultimatehall.org
WP="/usr/local/bin/wp --path=$SITE/files --skip-themes"
LOG=$SITE/logs/debug.log
alerts=0
alert() { echo "ALERT: $*"; alerts=$((alerts+1)); }
hr() { echo; echo "== $*"; }

hr "host"
echo "$(hostname)  $(date -u +'%F %T UTC')  php $(php -r 'echo PHP_VERSION;')"

hr "php errors, last 24h"
if [ -r "$LOG" ]; then
  P=$(mktemp); for h in $(seq 0 24); do date -u -d "-$h hour" +'[%d-%b-%Y %H'; done > "$P"
  # Scanners opening WordPress-internal files directly cause fatals that are noise.
  NOISE="in $SITE/files/(wp-includes|wp-admin/includes)/"
  grep -F -f "$P" "$LOG" > "$P.day" || true
  fatals=$(grep -E 'PHP (Fatal|Parse)' "$P.day" | grep -vcE "$NOISE" || true)
  warnings=$(grep -cE 'PHP (Warning|Deprecated|Notice)' "$P.day" || true)
  echo "fatals: $fatals  warnings and notices: $warnings"
  if [ "$fatals" -gt 0 ]; then
    alert "$fatals PHP fatal error(s) in the last 24h"
    grep -E 'PHP (Fatal|Parse)' "$P.day" | grep -vE "$NOISE" | tail -3 | cut -c1-300
  fi
  [ "$warnings" -gt 0 ] && grep -E 'PHP (Warning|Deprecated|Notice)' "$P.day" | sed -E 's/^\[[^]]*\] //' | sort | uniq -c | sort -rn | head -3 | cut -c1-250
  rm -f "$P" "$P.day"
else
  alert "cannot read $LOG"
fi

hr "disk"
used=$(df -P "$SITE" | awk 'NR==2{sub("%","",$5); print $5}')
echo "disk used ${used}%"
[ "${used:-100}" -gt 80 ] && alert "disk above 80% (${used}%)"

hr "file integrity"
if out=$($WP core verify-checksums 2>&1); then echo "core: ok"; else alert "WordPress core files don't match checksums"; echo "$out" | tail -5; fi
# Premium plugins have no public checksums and are skipped; a mismatch on any other plugin is an alert.
out=$($WP plugin verify-checksums --all 2>&1); echo "$out" | grep -E '^Success' || true
if echo "$out" | grep -qE "^Error|doesn't verify|File was added|File is missing|Checksum does not match"; then
  alert "plugin files don't match wordpress.org checksums"; echo "$out" | grep -vE 'Warning: (Could not retrieve|Couldn.t fetch)' | tail -5
fi

hr "updates"
echo "core $($WP core version)"
core_up=$($WP core check-update --field=version 2>/dev/null | grep -E '^[0-9]' | head -1)
[ -n "$core_up" ] && echo "core update available: $core_up"
$WP plugin list --update=available --fields=name,version,update_version --format=csv 2>/dev/null | tail -n +2 | sed 's/^/plugin update available: /'
echo "active plugins: $($WP plugin list --status=active --format=count 2>/dev/null)"

hr "cron"
# WP-Cron runs from the site user's crontab every minute; events more than an hour overdue mean it stopped.
overdue=$($WP eval 'echo count(array_filter(array_keys((array)_get_cron_array()), function($t){ return $t < time() - 3600; }));' 2>/dev/null)
echo "events overdue by more than an hour: ${overdue:-?}"
[ "${overdue:-0}" -gt 0 ] 2>/dev/null && alert "$overdue cron event(s) more than an hour overdue"

echo
if [ "$alerts" -eq 0 ]; then echo "RESULT: green"; exit 0; else echo "RESULT: $alerts alert(s)"; exit 1; fi
