#!/usr/bin/env bash
#
# Build a distributable WP OpenRag plugin ZIP.
#
# Usage:
#   ./bin/build-release.sh [version]
#
# - Default version is read from wp-openrag.php (WP_OPENRAG_VERSION constant).
# - Produces dist/wp-openrag-{version}.zip containing the plugin with vendor/
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
    VERSION="$(grep -E "WP_OPENRAG_VERSION" wp-openrag.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+" | head -1)"
    if [[ -z "${VERSION}" ]]; then
        echo "ERROR: could not detect version from wp-openrag.php" >&2
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

PKG_DIR="${STAGE}/wp-openrag"
mkdir -p "${PKG_DIR}"

echo "==> Copying source files"
# Include everything except dev/build artifacts.
rsync -a \
    --exclude='/vendor' \
    --exclude='/dist' \
    --exclude='/.git' \
    --exclude='/.github' \
    --exclude='/.zcode' \
    --exclude='/.wordpress-org' \
    --exclude='/bin' \
    --exclude='/docs' \
    --exclude='/node_modules' \
    --exclude='/composer.lock' \
    --exclude='/.gitignore' \
    --exclude='/.DS_Store' \
    --exclude='*.zip' \
    ./ "${PKG_DIR}/"

# ---- Install production dependencies ---------------------------------------
echo "==> Installing production dependencies"
( cd "${PKG_DIR}" && composer install --no-dev --prefer-dist --no-interaction )

# Remove composer tooling artifacts that aren't needed at runtime.
rm -f "${PKG_DIR}/composer.lock"

# ---- Package ----------------------------------------------------------------
echo "==> Packaging dist/wp-openrag-${VERSION}.zip"
mkdir -p "${DIST_DIR}"
OUT="${DIST_DIR}/wp-openrag-${VERSION}.zip"
rm -f "${OUT}"
( cd "${STAGE}" && zip -qr "${OUT}" wp-openrag )

# ---- Report -----------------------------------------------------------------
SIZE="$(du -h "${OUT}" | cut -f1)"
echo ""
echo "✔ Built ${OUT} (${SIZE})"
echo ""
echo "Next steps:"
echo "  1. Create a git tag:    git tag v${VERSION} && git push --tags"
echo "  2. Attach the ZIP to a GitHub Release, or upload via:"
echo "       gh release create v${VERSION} \"${OUT}\" --generate-notes"
