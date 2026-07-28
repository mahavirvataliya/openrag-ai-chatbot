#!/usr/bin/env bash
#
# Build a distributable OpenRag AI Chatbot plugin ZIP.
#
# Usage:
#   ./bin/build-release.sh [version]
#
# - Default version is read from openrag-ai-chatbot.php (OPENRAG_VERSION constant).
# - Produces dist/openrag-ai-chatbot-{version}.zip containing the plugin with vendor/
#   pre-built, no dev dependencies, no dev tooling.
# - Requires: bash 4+, php, composer, zip.
#
set -euo pipefail

# Resolve repository root (the plugin source directory).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"

cd "${ROOT_DIR}"

# ---- Determine version ------------------------------------------------------
VERSION="${1:-}"
if [[ -z "${VERSION}" ]]; then
    VERSION="$(grep -E "OPENRAG_VERSION" openrag-ai-chatbot.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+" | head -1)"
    if [[ -z "${VERSION}" ]]; then
        echo "ERROR: could not detect version from openrag-ai-chatbot.php" >&2
        exit 1
    fi
fi
echo "==> Building release ${VERSION}"

# ---- Sanity checks ----------------------------------------------------------
command -v php     >/dev/null 2>&1 || { echo "ERROR: php is required"     >&2; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "ERROR: composer is required" >&2; exit 1; }
command -v zip     >/dev/null 2>&1 || { echo "ERROR: zip is required"     >&2; exit 1; }

# ---- Lint all PHP files -----------------------------------------------------
echo "==> Linting PHP files"
LINT_FAIL=0
while IFS= read -r -d '' file; do
    if ! php -l "${file}" >/dev/null 2>&1; then
        php -l "${file}" || true
        LINT_FAIL=1
    fi
done < <(find . -name "*.php" -not -path "./vendor/*" -not -path "./dist/*" -print0)
if [[ "${LINT_FAIL}" -ne 0 ]]; then
    echo "ERROR: PHP lint failures detected" >&2
    exit 1
fi

# ---- Stage a clean build dir ------------------------------------------------
STAGE="$(mktemp -d)"
trap 'rm -rf "${STAGE}"' EXIT

PKG_DIR="${STAGE}/openrag-ai-chatbot"
mkdir -p "${PKG_DIR}"

echo "==> Copying source files"
# Include everything except dev/build artifacts.
# NOTE: exclude patterns have NO leading slash so they match at any depth
# (e.g. /.DS_Store would only match the repo root; .DS_Store matches nested
# ones like includes/.DS_Store too).
rsync -a \
    --exclude='vendor' \
    --exclude='dist' \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='.zcode' \
    --exclude='.wordpress-org' \
    --exclude='bin' \
    --exclude='docs' \
    --exclude='node_modules' \
    --exclude='composer.lock' \
    --exclude='.gitignore' \
    --exclude='.gitattributes' \
    --exclude='.gitmodules' \
    --exclude='.editorconfig' \
    --exclude='.phpcs.xml.dist' \
    --exclude='.distignore' \
    --exclude='*.zip' \
    --exclude='.DS_Store' \
    --exclude='.*.swp' \
    --exclude='.*.swo' \
    --exclude='Thumbs.db' \
    --exclude='ehthumbs.db' \
    --exclude='desktop.ini' \
    --exclude='CHAT_HISTORY.md' \
    --exclude='openrag-ai-chatbot-openrag-ai-chatbot-php-*.md' \
    --exclude='openrag-ai-chatbot-php-*.md' \
    ./ "${PKG_DIR}/"

# Belt-and-suspenders: purge any remaining hidden files/dirs that slipped
# through (OS cruft like .DS_Store, editor swap files, stray .dotfiles from
# vendored packages). Also strips dotfile junk that composer may install
# inside vendor/ (test stubs, .travis.yml, etc.).
find "${PKG_DIR}" -name '.*' -not -path "${PKG_DIR}/vendor/*" -delete 2>/dev/null || true

# ---- Install production dependencies ---------------------------------------
echo "==> Installing production dependencies"
( cd "${PKG_DIR}" && composer install --no-dev --prefer-dist --no-interaction )

# Remove composer tooling artifacts that aren't needed at runtime.
rm -f "${PKG_DIR}/composer.lock"

# Strip dotfile junk that composer packages sometimes ship (test stubs,
# .travis.yml, .distignore, editor configs). Only remove FILES named .* ,
# never the .git directories some packages embed (they're harmless but we
# drop them too for a clean archive).
find "${PKG_DIR}/vendor" \( -name '.*' -o -name '*.dist' -o -name 'phpunit.xml*' \) -delete 2>/dev/null || true
find "${PKG_DIR}/vendor" -type d -name '.git' -prune -exec rm -rf {} + 2>/dev/null || true

# ---- Verify no hidden files remain in the staged package --------------------
# A release ZIP must never contain OS cruft (.DS_Store), VCS metadata (.git,
# .github), editor swap files, or dev tooling. Fail loudly if any survive.
LEAKS="$(find "${PKG_DIR}" -name '.*' -not -name '.' 2>/dev/null)"
if [[ -n "${LEAKS}" ]]; then
    echo "ERROR: hidden files would leak into the release:" >&2
    echo "${LEAKS}" >&2
    exit 1
fi

# ---- Package ----------------------------------------------------------------
echo "==> Packaging dist/openrag-ai-chatbot-${VERSION}.zip"
mkdir -p "${DIST_DIR}"
OUT="${DIST_DIR}/openrag-ai-chatbot-${VERSION}.zip"
rm -f "${OUT}"
# zip with -x as a final guard: skip any dotfile even if rsync/find missed it.
( cd "${STAGE}" && zip -qr "${OUT}" openrag-ai-chatbot -x '*/.*' '*/.*/' '*.DS_Store' )

# ---- Report -----------------------------------------------------------------
SIZE="$(du -h "${OUT}" | cut -f1)"
echo ""
echo "✔ Built ${OUT} (${SIZE})"
echo ""
echo "Next steps:"
echo "  1. Create a git tag:    git tag v${VERSION} && git push --tags"
echo "  2. Attach the ZIP to a GitHub Release, or upload via:"
echo "       gh release create v${VERSION} \"${OUT}\" --generate-notes"
