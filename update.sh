#!/usr/bin/env bash
# Melody updater
set -euo pipefail

GREEN=$(tput setaf 2 2>/dev/null || true)
RESET=$(tput sgr0  2>/dev/null || true)

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Pulling latest changes..."
git -C "$SCRIPT_DIR" pull

echo "Syncing files to /opt/melody/..."
sudo rsync -a --delete \
    --exclude='.git' \
    --exclude='.env' \
    --exclude='.idea' \
    --exclude='node_modules' \
    "$SCRIPT_DIR/" /opt/melody/

echo ""
echo "${GREEN}Melody updated. Restart the app to apply changes.${RESET}"
