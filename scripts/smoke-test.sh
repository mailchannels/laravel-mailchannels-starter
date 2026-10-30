#!/bin/sh
# End-to-end check against a running app in stub mode.
#   APP=http://127.0.0.1:3006 STUB=http://127.0.0.1:4006 sh scripts/smoke-test.sh
set -eu
APP="${APP:-http://127.0.0.1:3006}"
STUB="${STUB:-http://127.0.0.1:4006}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

csrf() { curl -s -c "$JAR" -b "$JAR" "$APP$1" | sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' | head -n1; }
messages() { curl -s "$STUB/_stub/messages"; }
count() { messages | grep -c '"endpoint"' || true; }
fail() { echo "FAIL: $*" >&2; exit 1; }

curl -s -X DELETE "$STUB/_stub/messages" >/dev/null

echo "== 1. sign up user@example.test"
T=$(csrf /signup)
code=$(curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" -X POST "$APP/signup" \
  --data-urlencode "_token=$T" --data-urlencode email=user@example.test \
  --data-urlencode password=correct-horse --data-urlencode password_confirmation=correct-horse)
echo "POST /signup -> $code"
link=$(messages | grep -o "$APP/verify?token=[A-Za-z0-9_-]*" | head -n1)
[ -n "$link" ] || fail "no verification email at stub"
echo "stub received verification email: $link"
curl -s "$link" | grep -q "Email verified" || fail "verification link did not verify"
echo "GET verify link -> Email verified"
code=$(curl -s -o /dev/null -w '%{http_code}' "$link")
[ "$code" = 400 ] || fail "reused verification link returned $code"
echo "GET verify link again -> $code (single use)"

echo "== 2. password reset"
curl -s -X DELETE "$STUB/_stub/messages" >/dev/null
rm -f "$JAR"; JAR="$(mktemp)"
T=$(csrf /forgot-password)
code=$(curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" -X POST "$APP/forgot-password" \
  --data-urlencode "_token=$T" --data-urlencode email=user@example.test)
echo "POST /forgot-password -> $code"
link=$(messages | grep -o "$APP/reset-password?token=[A-Za-z0-9]*&email=[A-Za-z0-9%._-]*" | head -n1)
[ -n "$link" ] || fail "no reset email at stub"
echo "stub received reset email: $link"

echo "== 3. contact form"
curl -s -X DELETE "$STUB/_stub/messages" >/dev/null
T=$(csrf /contact)
code=$(curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" -X POST "$APP/contact" \
  --data-urlencode "_token=$T" --data-urlencode "name=Ada Lovelace" \
  --data-urlencode email=ada@example.test --data-urlencode "message=Hello from the smoke test")
echo "POST /contact -> $code"
messages | grep -q '"reply_to"' || fail "contact email missing reply_to"
messages | tr -d ' \n' | grep -o '"reply_to":{[^}]*}' | head -n1
[ "$(count)" = 1 ] || fail "expected 1 contact email"

echo "== 4. webhooks"
webhook() { php stub/send-webhook.php --url="$APP/webhooks/mailchannels" "$@" > /tmp/webhook.out; rc=$?; tail -n 2 /tmp/webhook.out; return $rc; }
webhook || fail "signed webhook rejected"
for variant in --tamper --bad-signature --stale; do
  echo "-- $variant"
  if webhook "$variant"; then fail "webhook $variant accepted"; fi
done

echo "ALL SMOKE CHECKS PASSED"
