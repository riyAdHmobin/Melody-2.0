#!/usr/bin/env bash
# Melody uninstaller
set -euo pipefail

GREEN=$(tput setaf 2 2>/dev/null || true)
RED=$(tput setaf 1 2>/dev/null || true)
RESET=$(tput sgr0  2>/dev/null || true)

echo "This will remove Melody from your system."
read -rp "Continue? [y/N] " confirm
[[ "${confirm,,}" == "y" ]] || { echo "Cancelled."; exit 0; }

sudo rm -rf  /opt/melody
sudo rm -f   /usr/local/bin/melody
rm -f        "$HOME/.local/share/applications/melody.desktop"

read -rp "Also remove database config (~/.config/melody/)? [y/N] " rmconf
if [[ "${rmconf,,}" == "y" ]]; then
    rm -rf "$HOME/.config/melody"
    echo "  Config removed."
fi

echo ""
echo "${GREEN}Melody uninstalled.${RESET}"
echo "Note: Electron (npm global) was not removed. To remove it:"
echo "  sudo npm uninstall -g electron"
