#!/usr/bin/env bash
# Richtet ıgnıs mit Docker auf einem Linux-Server ein:
#
#   curl -fsSL https://raw.githubusercontent.com/EmergencyForge/ignis/main/docker/install.sh | sudo bash
#
# Fragt Domain und Discord-Zugang ab, erzeugt das Datenbank-Passwort,
# schreibt die .env und startet docker-compose.prod.yml, auf Wunsch mit
# Caddy für HTTPS. Ohne Rückfragen (cloud-init, Ansible) vorher setzen:
# IGNIS_DOMAIN, DISCORD_CLIENT_ID, DISCORD_CLIENT_SECRET, IGNIS_HTTPS=j|n.
# IGNIS_DIR ändert den Zielordner (Vorgabe /opt/ignis).
set -euo pipefail

dir="${IGNIS_DIR:-/opt/ignis}"
raw="${IGNIS_RAW_URL:-https://raw.githubusercontent.com/EmergencyForge/ignis/main}"
compose=(docker compose -f docker-compose.prod.yml)

fail() { printf '\nFehler: %s\n' "$1" >&2; exit 1; }

# Bei curl | bash ist stdin das Skript selbst, die Antworten kommen deshalb
# vom Terminal. Schon gesetzte Variablen werden nicht erfragt.
ask() {
    local var=$1 prompt=$2
    [ -n "${!var+x}" ] && return 0
    (: < /dev/tty) 2> /dev/null || fail "$var ist nicht gesetzt und es gibt kein Terminal für die Rückfrage."
    if [ "${3:-}" = geheim ]; then
        read -rsp "$prompt: " "$var" < /dev/tty
        echo
    else
        read -rp "$prompt: " "$var" < /dev/tty
    fi
}

no() { [[ ${1:-} =~ ^[nN0] ]]; }

if ! command -v docker > /dev/null; then
    ask IGNIS_INSTALL_DOCKER "Docker fehlt. Jetzt über get.docker.com installieren? (J/n)"
    no "$IGNIS_INSTALL_DOCKER" && fail "Ohne Docker geht es nicht: https://docs.docker.com/engine/install/"
    curl -fsSL https://get.docker.com | sh
fi
docker info > /dev/null 2>&1 || fail "Docker läuft nicht oder dir fehlen die Rechte. Starte das Skript mit sudo."
docker compose version > /dev/null 2>&1 || fail "Das Compose-Plugin fehlt (Paket docker-compose-plugin)."

[ -e "$dir/.env" ] && fail "In $dir ist ıgnıs schon eingerichtet. Update: cd $dir && ${compose[*]} pull && ${compose[*]} up -d"
mkdir -p "$dir" 2> /dev/null || fail "$dir lässt sich nicht anlegen. Mit sudo starten oder IGNIS_DIR setzen."
cd "$dir"

ask IGNIS_DOMAIN "Domain für ıgnıs, z.B. ignis.example.de"
IGNIS_DOMAIN=${IGNIS_DOMAIN#http*://}
IGNIS_DOMAIN=${IGNIS_DOMAIN%/}
[[ $IGNIS_DOMAIN =~ ^[A-Za-z0-9.-]+(:[0-9]{1,5})?$ ]] || fail "Ungültige Domain: $IGNIS_DOMAIN"

echo
echo "Trag in deiner Discord-Anwendung (discord.com/developers/applications)"
echo "unter OAuth2 › Redirects diese Adresse ein:"
echo "  https://$IGNIS_DOMAIN/auth/callback.php"
echo
ask DISCORD_CLIENT_ID "Discord Client ID"
[[ $DISCORD_CLIENT_ID =~ ^[0-9]{17,20}$ ]] || fail "Die Client ID ist eine 17- bis 20-stellige Zahl."
ask DISCORD_CLIENT_SECRET "Discord Client Secret" geheim
# Die Prüfung hält auch $ und Anführungszeichen aus der .env fern, die Compose sonst auswertet.
[[ $DISCORD_CLIENT_SECRET =~ ^[A-Za-z0-9_-]+$ ]] || fail "Das Client Secret enthält unerwartete Zeichen."
ask IGNIS_HTTPS "HTTPS mit Caddy einrichten? Nein nur, wenn schon ein Reverse Proxy läuft (J/n)"

curl -fsSL "$raw/docker-compose.prod.yml" -o docker-compose.prod.yml \
    || fail "docker-compose.prod.yml ließ sich nicht von $raw laden."

umask 077
cat > .env << EOF
# Angelegt von install.sh. Alle Optionen: $raw/docker/.env.example
IMAGE_TAG=
DB_NAME=ignis
DB_USER=ignis
DB_PASS=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
DISCORD_CLIENT_ID=$DISCORD_CLIENT_ID
DISCORD_CLIENT_SECRET=$DISCORD_CLIENT_SECRET
SYSTEM_URL=https://$IGNIS_DOMAIN
EOF
if ! no "$IGNIS_HTTPS"; then
    echo "COMPOSE_PROFILES=https" >> .env
fi

echo
echo "Starte ıgnıs. Beim ersten Mal lädt Docker die Images, das dauert etwas."
"${compose[@]}" up -d

status=
for _ in $(seq 60); do
    status=$(docker inspect -f '{{.State.Health.Status}}' "$("${compose[@]}" ps -q app)" 2> /dev/null || true)
    [ "$status" = healthy ] && break
    sleep 3
done
[ "$status" = healthy ] || fail "ıgnıs ist nach drei Minuten nicht bereit. Logs: cd $dir && ${compose[*]} logs app"

echo
echo "ıgnıs läuft: https://$IGNIS_DOMAIN"
if no "$IGNIS_HTTPS"; then
    echo "Leite deinen Reverse Proxy auf 127.0.0.1:8080 weiter, mit den Headern"
    echo "X-Forwarded-Proto und X-Forwarded-Host."
else
    echo "Das Zertifikat holt Caddy selbst. Dafür muss die Domain auf diesen Server"
    echo "zeigen und die Ports 80 und 443 müssen in der Firewall offen sein."
fi
echo
echo "Melde dich jetzt gleich mit Discord an: Wer sich zuerst anmeldet, wird Administrator."
echo "Zugangsdaten liegen in $dir/.env. Update: cd $dir && ${compose[*]} pull && ${compose[*]} up -d"
