#!/usr/bin/env bash
#
# Generate the languages/openrag-ai-chatbot.pot translation template.
#
# Primary method: wp-cli i18n make-pot (handles PHP, JS, and proper plural forms).
# Fallback: xgettext (if wp-cli isn't available; PHP-only).
#
# Translators consume this .pot to produce openrag-ai-chatbot-<locale>.po/.mo files
# which are then committed under languages/.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUT="${ROOT_DIR}/languages/openrag-ai-chatbot.pot"

cd "${ROOT_DIR}"
mkdir -p languages

if command -v wp >/dev/null 2>&1; then
    echo "==> Generating POT via wp-cli i18n make-pot"
    wp i18n make-pot . "${OUT}" \
        --domain="openrag-ai-chatbot" \
        --package-name="OpenRag AI Chatbot" \
        --exclude="vendor,dist,.git,.github" \
        --headers='{"Report-Msgid-Bugs-To":"https://github.com/mahavirvataliya/openrag-ai-chatbot/issues","POT-Creation-Date":""}' \
        --allow-root
elif command -v xgettext >/dev/null 2>&1; then
    echo "==> wp-cli not found — falling back to xgettext (PHP only)"
    xgettext \
        --language=PHP \
        --from-code=UTF-8 \
        --keyword=__ \
        --keyword=_e \
        --keyword=_x:1,2c \
        --keyword=esc_html__ \
        --keyword=esc_html_e \
        --keyword=esc_attr__ \
        --keyword=esc_attr_e \
        --keyword=esc_attr_x:1,2c \
        --keyword=wp_json_encode:1 \
        --package-name="OpenRag AI Chatbot" \
        --msgid-bugs-address="https://github.com/mahavirvataliya/openrag-ai-chatbot/issues" \
        --output="${OUT}" \
        $(find . -name "*.php" -not -path "./vendor/*" -not -path "./dist/*")
    # Strip xgettext's default charset header so the file is consistent.
    sed -i.bak -e 's/"Content-Type: text\/plain; charset=CHARSET/"Content-Type: text\/plain; charset=UTF-8/' "${OUT}" && rm -f "${OUT}.bak"
else
    echo "ERROR: neither wp-cli nor xgettext is installed." >&2
    echo "Install wp-cli:  curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar" >&2
    exit 1
fi

echo "✔ Wrote ${OUT}"
echo "  $(grep -c '^msgid' "${OUT}") translatable strings"

# Recompile any existing .po files into .mo for runtime.
shopt -s nullglob
for po in languages/*.po; do
    if command -v msgfmt >/dev/null 2>&1; then
        msgfmt -o "${po%.po}.mo" "${po}" && echo "  ✓ Compiled $(basename "${po%.po}.mo")"
    fi
done
