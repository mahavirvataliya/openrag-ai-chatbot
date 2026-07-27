#!/usr/bin/env bash
#
# Deploy WP OpenRag to the WordPress.org plugin SVN repository.
#
# This is the canonical release mechanism for WordPress.org. After your
# plugin is approved (you've received the "wp-openrag" slug and SVN URL),
# this script:
#
#   1. Stages a clean build (composer install --no-dev).
#   2. Checks out the SVN repo to a temp dir.
#   3. Syncs trunk/ with the build, bumping /assets/ and /tags/ as needed.
#   4. Commits the changes with a meaningful message.
#
# Prerequisites
# -------------
#   • Your plugin was approved and you received SVN access.
#   • svn is installed (`brew install svn` / `apt-get install subversion`).
#   • You have saved your WordPress.org credentials. The script will
#     prompt for them or read SVN_USERNAME / SVN_PASSWORD env vars.
#
# Usage
# -----
#   bin/deploy-wordpress-org.sh                       # uses WP_OPENRAG_VERSION
#   bin/deploy-wordpress-org.sh 1.0.0                 # explicit version
#   bin/deploy-wordpress-org.sh 1.0.0 --tag           # also creates /tags/1.0.0
#   bin/deploy-wordpress-org.sh 1.0.0 --tag --dry-run # no svn commit
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
SLUG="wp-openrag"
SVN_URL="https://plugins.svn.wordpress.org/${SLUG}"

# ---- Parse args -------------------------------------------------------------
VERSION=""
DO_TAG=0
DRY_RUN=0
for arg in "$@"; do
    case "${arg}" in
        --tag)    DO_TAG=1 ;;
        --dry-run) DRY_RUN=1 ;;
        --help|-h)
            sed -n '2,/^set -euo/p' "$0" | sed 's/^# \?//'
            exit 0 ;;
        *) VERSION="${arg}" ;;
    esac
done

# ---- Resolve version --------------------------------------------------------
if [[ -z "${VERSION}" ]]; then
    VERSION="$(grep -E "WP_OPENRAG_VERSION" wp-openrag.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+" | head -1)"
fi
if [[ -z "${VERSION}" ]]; then
    echo "ERROR: could not determine version" >&2; exit 1
fi
echo "==> Deploying ${SLUG} ${VERSION} to WordPress.org SVN"

# ---- Sanity checks ----------------------------------------------------------
command -v svn >/dev/null 2>&1 || { echo "ERROR: svn is required (brew install svn / apt install subversion)" >&2; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "ERROR: composer is required" >&2; exit 1; }

if [[ ! -f "${ROOT_DIR}/.wordpress-org/assets/icon-128.png" ]]; then
    echo "==> Generating plugin assets first"
    "${SCRIPT_DIR}/generate-wordpress-assets.sh"
fi

# ---- Stage the build --------------------------------------------------------
echo "==> Staging a clean production build"
STAGE="$(mktemp -d)"
BUILD_DIR="${STAGE}/build"
trap 'rm -rf "${STAGE}"' EXIT

rsync -a \
    --exclude='/vendor' --exclude='/dist' --exclude='/.git' --exclude='/.github' \
    --exclude='/.zcode' --exclude='/node_modules' --exclude='/.wordpress-org' \
    --exclude='/bin' --exclude='/docs' --exclude='/.gitignore' \
    --exclude='/composer.lock' --exclude='*.zip' --exclude='/.DS_Store' \
    "${ROOT_DIR}/" "${BUILD_DIR}/"

( cd "${BUILD_DIR}" && composer install --no-dev --prefer-dist --no-interaction )
rm -f "${BUILD_DIR}/composer.lock"

# ---- SVN checkout -----------------------------------------------------------
echo "==> Checking out ${SVN_URL}"
SVN_DIR="${STAGE}/svn"
svn checkout -q "${SVN_URL}" "${SVN_DIR}" --depth=immediates
# Get trunk + assets fully.
svn update -q "${SVN_DIR}/trunk" "${SVN_DIR}/assets"

# ---- Sync trunk -------------------------------------------------------------
echo "==> Syncing trunk/"
TRUNK="${SVN_DIR}/trunk"

# Remove everything currently in trunk (except .svn).
find "${TRUNK}" -mindepth 1 -maxdepth 1 ! -name '.svn' -exec rm -rf {} +

# Copy the build in.
rsync -a --exclude='.svn' "${BUILD_DIR}/" "${TRUNK}/"

# Make sure languages/ and templates/ exist in trunk (they ship with the plugin).
mkdir -p "${TRUNK}/languages"

# ---- Sync /assets/ ----------------------------------------------------------
echo "==> Syncing /assets/"
ASSETS_SRC="${ROOT_DIR}/.wordpress-org/assets"
ASSETS_DST="${SVN_DIR}/assets"
mkdir -p "${ASSETS_DST}"

# Clean existing assets (except .svn).
find "${ASSETS_DST}" -mindepth 1 -maxdepth 1 ! -name '.svn' -exec rm -rf {} +

# Copy only the files wp.org recognizes: icon-*.png, banner-*.png, screenshot-*.png, plus the SVGs.
for f in icon-128.png icon-256.png icon.svg \
         banner-772x250.png banner-1544x500.png banner.svg; do
    if [[ -f "${ASSETS_SRC}/${f}" ]]; then
        cp "${ASSETS_SRC}/${f}" "${ASSETS_DST}/"
    fi
done
# Screenshots (readme.txt references them).
if [[ -d "${ROOT_DIR}/.wordpress-org/screenshots" ]]; then
    for f in "${ROOT_DIR}/.wordpress-org/screenshots/"*.png; do
        [[ -f "$f" ]] && cp "$f" "${ASSETS_DST}/"
    done
fi

# ---- Optionally tag ---------------------------------------------------------
if [[ "${DO_TAG}" -eq 1 ]]; then
    echo "==> Creating /tags/${VERSION}"
    TAG_DIR="${SVN_DIR}/tags/${VERSION}"
    svn cp -q "${TRUNK}" "${TAG_DIR}"
fi

# ---- svn add / status -------------------------------------------------------
echo "==> Reconciling SVN state"
cd "${SVN_DIR}"
# Add any new files.
svn add --force -q . >/dev/null 2>&1 || true
# Remove from SVN anything that was deleted.
svn st | grep '^\!' | awk '{print $2}' | while read -r f; do
    svn rm -q "$f" || true
done

echo ""
echo "==> SVN status preview:"
svn st | head -40

# ---- Show what's about to be committed --------------------------------------
echo ""
echo "Press ENTER to commit, or Ctrl-C to abort."
[[ "${DRY_RUN}" -eq 1 ]] && echo "(DRY RUN — nothing will actually be committed)"
read -r -e < /dev/tty || true

if [[ "${DRY_RUN}" -eq 1 ]]; then
    echo "==> Dry run — skipping commit"
    exit 0
fi

# ---- Commit -----------------------------------------------------------------
MSG="Release ${VERSION}"
if [[ "${DO_TAG}" -eq 1 ]]; then
    MSG="${MSG} (tagged)"
fi

echo "==> Committing: ${MSG}"
if [[ -n "${SVN_USERNAME:-}" ]]; then
    svn commit -m "${MSG}" --username "${SVN_USERNAME}" --password "${SVN_PASSWORD:-}" --non-interactive
else
    svn commit -m "${MSG}"
fi

echo ""
echo "✔ Deployed to ${SVN_URL}"
echo ""
echo "Within ~15 minutes WordPress.org will rebuild the ZIP and your release will be"
echo "live at https://wordpress.org/plugins/${SLUG}/"
echo ""
echo "Next steps:"
echo "  • Verify the plugin page renders with the new banner/icon."
echo "  • Click 'Development Log' to confirm the commit landed."
echo "  • Tag git too (if not already):  git tag v${VERSION} && git push --tags"
