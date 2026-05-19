#!/usr/bin/env bash
# Melody updater
# Usage (local):  bash update.sh
# Usage (remote): bash <(curl -fsSL https://raw.githubusercontent.com/riyAdHmobin/Melody-2.0/main/update.sh)
set -euo pipefail

REPO_URL="https://github.com/riyAdHmobin/Melody-2.0.git"
GREEN=$(tput setaf 2 2>/dev/null || true)
RESET=$(tput sgr0  2>/dev/null || true)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if git -C "$SCRIPT_DIR" rev-parse --git-dir &>/dev/null; then
    # Running from a local git clone — pull in place
    echo "Pulling latest changes..."
    git -C "$SCRIPT_DIR" pull
    SRC="$SCRIPT_DIR"
else
    # Running via curl — clone to a temp dir
    echo "Fetching latest Melody from GitHub..."
    TMP_DIR=$(mktemp -d)
    git clone --depth=1 "$REPO_URL" "$TMP_DIR/melody"
    SRC="$TMP_DIR/melody"
fi

echo "Ensuring php-xml is installed..."
sudo apt-get install -y php-xml -qq

echo "Syncing files to /opt/melody/..."
sudo rsync -a --delete \
    --exclude='.git' \
    --exclude='.env' \
    --exclude='.idea' \
    --exclude='node_modules' \
    "$SRC/" /opt/melody/

echo ""
echo "${GREEN}Melody updated. Restart the app to apply changes.${RESET}"
