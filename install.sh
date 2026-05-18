#!/usr/bin/env bash
# Melody installer — Ubuntu/Debian
# Usage: bash install.sh
set -euo pipefail

BOLD=$(tput bold  2>/dev/null || true)
GREEN=$(tput setaf 2 2>/dev/null || true)
YELLOW=$(tput setaf 3 2>/dev/null || true)
RED=$(tput setaf 1 2>/dev/null || true)
RESET=$(tput sgr0  2>/dev/null || true)

step() { echo ""; echo "${GREEN}▶${RESET} ${BOLD}$*${RESET}"; }
info() { echo "  $*"; }
warn() { echo "  ${YELLOW}⚠  $*${RESET}"; }
die()  { echo "${RED}✖ $*${RESET}" >&2; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALL_DIR="/opt/melody"
CONFIG_DIR="$HOME/.config/melody"
BIN_PATH="/usr/local/bin/melody"
DESKTOP_FILE="$HOME/.local/share/applications/melody.desktop"

# ── OS check ────────────────────────────────────────────────────────────────
[[ -f /etc/debian_version ]] || die "This installer requires Ubuntu or Debian."

# ── 1. PHP ──────────────────────────────────────────────────────────────────
step "Installing PHP"
sudo apt-get update -qq
sudo apt-get install -y php-cli php-mysql php-mbstring php-curl
info "$(php --version | head -1)"

# ── 2. Node.js ──────────────────────────────────────────────────────────────
if ! command -v node &>/dev/null; then
    NODE_MAJOR=0
else
    NODE_MAJOR=$(node -e 'console.log(+process.version.slice(1).split(".")[0])')
fi

if [[ "$NODE_MAJOR" -lt 18 ]]; then
    step "Installing Node.js 20"
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - >/dev/null
    sudo apt-get install -y nodejs
    info "$(node --version)"
else
    info "Node $(node --version) already installed"
fi

# ── 3. Electron ─────────────────────────────────────────────────────────────
if ! command -v electron &>/dev/null; then
    step "Installing Electron (~150 MB download)"
    sudo npm install -g electron --unsafe-perm --silent
fi
info "Electron $(electron --version)"

# ── 4. Copy app files ───────────────────────────────────────────────────────
step "Installing Melody to $INSTALL_DIR"
sudo mkdir -p "$INSTALL_DIR"
sudo rsync -a --delete \
    --exclude='.git' \
    --exclude='.env' \
    --exclude='.idea' \
    --exclude='node_modules' \
    "$SCRIPT_DIR/" "$INSTALL_DIR/"
sudo chown -R root:root "$INSTALL_DIR"
sudo chmod -R 755 "$INSTALL_DIR"

# ── 5. Launcher script ──────────────────────────────────────────────────────
step "Creating launcher"
sudo tee "$BIN_PATH" > /dev/null <<'EOF'
#!/usr/bin/env bash
exec electron --no-sandbox /opt/melody/electron/ "$@"
EOF
sudo chmod +x "$BIN_PATH"

# Fix Electron sandbox permissions
if [[ -f /usr/local/lib/node_modules/electron/dist/chrome-sandbox ]]; then
    sudo chown root:root /usr/local/lib/node_modules/electron/dist/chrome-sandbox
    sudo chmod 4755     /usr/local/lib/node_modules/electron/dist/chrome-sandbox
fi

# ── 6. Desktop entry ────────────────────────────────────────────────────────
mkdir -p "$(dirname "$DESKTOP_FILE")"
cat > "$DESKTOP_FILE" <<EOF
[Desktop Entry]
Name=Melody
Comment=YouTube playlist manager & music player
Exec=$BIN_PATH
Icon=$INSTALL_DIR/assets/icons/logo.svg
Type=Application
Categories=AudioVideo;Music;Player;
StartupNotify=true
EOF
info "Desktop entry created → search 'Melody' in your app launcher"

# ── 7. Database credentials ─────────────────────────────────────────────────
step "Database configuration"
echo "  Enter your remote MySQL connection details."
echo ""
read -rp  "  DB Host:             " DB_HOST
read -rp  "  DB Port [3306]:      " DB_PORT;  DB_PORT=${DB_PORT:-3306}
read -rp  "  DB Name [melody]:    " DB_NAME;  DB_NAME=${DB_NAME:-melody}
read -rp  "  DB User [melody]:    " DB_USER;  DB_USER=${DB_USER:-melody}
read -rsp "  DB Password:         " DB_PASS;  echo

# ── 8. Admin account ────────────────────────────────────────────────────────
echo ""
step "Admin account (for /admin.php)"
read -rp  "  Username [admin]:    " ADMIN_USER; ADMIN_USER=${ADMIN_USER:-admin}
read -rsp "  Password:            " ADMIN_PASS; echo
ADMIN_HASH=$(ADMIN_PASS="$ADMIN_PASS" php -r "echo password_hash(getenv('ADMIN_PASS'), PASSWORD_BCRYPT);")

# ── 9. Write config ─────────────────────────────────────────────────────────
mkdir -p "$CONFIG_DIR"
chmod 700 "$CONFIG_DIR"
{
    printf 'DB_HOST=%s\n'               "$DB_HOST"
    printf 'DB_PORT=%s\n'               "$DB_PORT"
    printf 'DB_NAME=%s\n'               "$DB_NAME"
    printf 'DB_USER=%s\n'               "$DB_USER"
    printf 'DB_PASS=%s\n'               "$DB_PASS"
    printf 'MELODY_ADMIN_USER=%s\n'     "$ADMIN_USER"
    printf 'MELODY_ADMIN_PASS_HASH=%s\n' "$ADMIN_HASH"
} > "$CONFIG_DIR/.env"
chmod 600 "$CONFIG_DIR/.env"
info "Config saved to $CONFIG_DIR/.env"

# ── Done ────────────────────────────────────────────────────────────────────
echo ""
echo "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
echo "${GREEN}  Melody installed successfully${RESET}"
echo "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
echo ""
echo "  Launch from app menu  →  search 'Melody'"
echo "  Launch from terminal  →  melody"
echo "  Change DB settings    →  edit $CONFIG_DIR/.env"
echo "  Admin panel           →  open /admin.php inside the app"
echo ""
